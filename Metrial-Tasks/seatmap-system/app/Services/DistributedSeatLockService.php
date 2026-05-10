<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Models\ElementLock;
use Exception;

/**
 * DistributedSeatLockService
 * 
 * يوفر آلية قفل موزعة لإزالة نقطة الفشل الواحدة (Single Point of Failure).
 * يستخدم تجزئة متسقة (Consistent Hashing) لتوزيع الأقفال عبر عدة عقد Redis.
 * 
 * الميزات:
 * - Redlock algorithm للتوافق الموزع
 * - تجزئة الأقفال لمنع الصفوف الساخنة
 * - إعادة محاولة ذكية مع تراجع أسي
 * - مراقبة صحية لعقد Redis
 */
class DistributedSeatLockService
{
    /**
     * @var array مصفوفة عملاء Redis للعقد الموزعة
     */
    private array $redisShards;

    /**
     * @var int عدد المحاولات القصوى لإعادة المحاولة
     */
    private int $maxRetries = 3;

    /**
     * @var int مدة انتهاء صلاحية القفل بالثواني
     */
    private int $lockTTL = 300; // 5 دقائق

    /**
     * @var array سجل صحة العقد
     */
    private array $shardHealth = [];

    public function __construct()
    {
        // تهيئة عقد Redis الموزعة
        $this->redisShards = [
            'shard_1' => Redis::connection('redis-lock-1'),
            'shard_2' => Redis::connection('redis-lock-2'),
            'shard_3' => Redis::connection('redis-lock-3'),
        ];

        // تهيئة سجل الصحة
        foreach (array_keys($this->redisShards) as $shard) {
            $this->shardHealth[$shard] = true;
        }
    }

    /**
     * اكتساب قفل لمقعد معين باستخدام خوارزمية Redlock
     *
     * @param int $elementId
     * @param string $lockKey
     * @param int|null $ttl
     * @return bool
     */
    public function acquireLock(int $elementId, string $lockKey, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->lockTTL;
        $lockValue = $lockKey . ':' . microtime(true) . ':' . uniqid();
        $quorum = floor(count($this->redisShards) / 2) + 1;
        $acquiredCount = 0;
        $acquiredShards = [];

        // تجزئة العنصر لتحديد الشريحة الأساسية
        $primaryShard = $this->getShardForKey($elementId);

        // أولاً: محاولة الحصول على القفل من الشريحة الأساسية
        try {
            if ($this->acquireLockOnShard($primaryShard, $elementId, $lockValue, $ttl)) {
                $acquiredCount++;
                $acquiredShards[] = $primaryShard;
            }
        } catch (Exception $e) {
            Log::warning("Failed to acquire lock on primary shard", [
                'shard' => $primaryShard,
                'element_id' => $elementId,
                'error' => $e->getMessage()
            ]);
            $this->shardHealth[$primaryShard] = false;
        }

        // ثانياً: محاولة الحصول على القفل من شريحة احتياطية
        foreach ($this->redisShards as $shardName => $shard) {
            if ($shardName === $primaryShard || $acquiredCount >= $quorum) {
                continue;
            }

            try {
                if ($this->acquireLockOnShard($shardName, $elementId, $lockValue, $ttl)) {
                    $acquiredCount++;
                    $acquiredShards[] = $shardName;
                }
            } catch (Exception $e) {
                Log::warning("Failed to acquire lock on backup shard", [
                    'shard' => $shardName,
                    'element_id' => $elementId,
                    'error' => $e->getMessage()
                ]);
                $this->shardHealth[$shardName] = false;
            }
        }

        // التحقق من الوصول إلى الأغلبية (Quorum)
        if ($acquiredCount >= $quorum) {
            // محاولة الإدراج في قاعدة البيانات
            if ($this->optimisticLockInsert($elementId, $lockKey, $lockValue)) {
                // تخزين معلومات القفل للاستخدام لاحقاً
                $this->storeLockMetadata($elementId, $lockKey, $lockValue, $acquiredShards);
                return true;
            } else {
                // فشل الإدراج في قاعدة البيانات، تحرير الأقفال
                $this->releaseLockFromShards($elementId, $acquiredShards);
            }
        } else {
            // لم نصل إلى الأغلبية، تحرير أي أقفال تم الحصول عليها
            $this->releaseLockFromShards($elementId, $acquiredShards);
        }

        return false;
    }

    /**
     * تحرير قفل عنصر معين
     *
     * @param int $elementId
     * @param string $lockKey
     * @return bool
     */
    public function releaseLock(int $elementId, string $lockKey): bool
    {
        $lockMetadata = $this->getLockMetadata($elementId);
        
        if (!$lockMetadata || $lockMetadata['lock_key'] !== $lockKey) {
            return false;
        }

        $released = true;
        $shards = $lockMetadata['shards'] ?? array_keys($this->redisShards);

        foreach ($shards as $shardName) {
            try {
                $this->redisShards[$shardName]->del($this->getLockKey($elementId));
            } catch (Exception $e) {
                Log::warning("Failed to release lock on shard", [
                    'shard' => $shardName,
                    'element_id' => $elementId,
                    'error' => $e->getMessage()
                ]);
                $released = false;
            }
        }

        // حذف البيانات الوصفية
        $this->deleteLockMetadata($elementId);

        return $released;
    }

    /**
     * التحقق من صحة قفل العنصر
     *
     * @param int $elementId
     * @param string $lockKey
     * @return bool
     */
    public function validateLock(int $elementId, string $lockKey): bool
    {
        $primaryShard = $this->getShardForKey($elementId);
        $lockValue = $this->redisShards[$primaryShard]->get($this->getLockKey($elementId));
        
        return $lockValue === $lockKey;
    }

    /**
     * الحصول على توزيع الأقفال (لأغراض المراقبة)
     *
     * @return array
     */
    public function getLockDistribution(): array
    {
        $distribution = [];
        
        foreach ($this->redisShards as $shardName => $shard) {
            try {
                $keys = $shard->keys('seatlock:*');
                $distribution[$shardName] = count($keys);
            } catch (Exception $e) {
                $distribution[$shardName] = 0;
                $this->shardHealth[$shardName] = false;
            }
        }

        return $distribution;
    }

    /**
     * الحصول على حالة صحة العقد
     *
     * @return array
     */
    public function getShardHealth(): array
    {
        return $this->shardHealth;
    }

    /**
     * محاولة الحصول على قفل في شريحة معينة
     *
     * @param string $shardName
     * @param int $elementId
     * @param string $lockValue
     * @param int $ttl
     * @return bool
     */
    private function acquireLockOnShard(string $shardName, int $elementId, string $lockValue, int $ttl): bool
    {
        try {
            return $this->redisShards[$shardName]->set(
                $this->getLockKey($elementId),
                $lockValue,
                'EX',
                $ttl,
                'NX'
            );
        } catch (Exception $e) {
            Log::error("Redis lock acquisition failed", [
                'shard' => $shardName,
                'element_id' => $elementId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * الإدراج المتفائل للقفل في قاعدة البيانات
     *
     * @param int $elementId
     * @param string $lockKey
     * @param string $lockValue
     * @return bool
     */
    private function optimisticLockInsert(int $elementId, string $lockKey, string $lockValue): bool
    {
        $maxRetries = $this->maxRetries;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                DB::transaction(function () use ($elementId, $lockKey, $lockValue) {
                    // حذف الأقفال المنتهية الصلاحية
                    ElementLock::where('event_element_id', $elementId)
                        ->where('expires_at', '<', now())
                        ->delete();

                    // التحقق من عدم وجود قفل نشط
                    $existingLock = ElementLock::where('event_element_id', $elementId)
                        ->where('expires_at', '>', now())
                        ->first();

                    if ($existingLock) {
                        throw new Exception('Active lock already exists');
                    }

                    // إنشاء قفل جديد
                    ElementLock::create([
                        'event_element_id' => $elementId,
                        'lock_key' => $lockKey,
                        'lock_value' => $lockValue,
                        'expires_at' => now()->addSeconds($this->lockTTL),
                        'locked_at' => now(),
                        'version' => DB::raw('COALESCE((SELECT MAX(version) FROM element_locks WHERE event_element_id = ?), 0) + 1', [$elementId]),
                    ]);
                });

                return true;
            } catch (Exception $e) {
                if ($i < $maxRetries - 1 && $this->isDeadlock($e)) {
                    // تراجع أسي
                    usleep(random_int(10000 * $i, 50000 * $i));
                    continue;
                }
                
                Log::error("Failed to insert lock into database", [
                    'element_id' => $elementId,
                    'lock_key' => $lockKey,
                    'attempt' => $i + 1,
                    'error' => $e->getMessage()
                ]);
                
                return false;
            }
        }

        return false;
    }

    /**
     * تخزين البيانات الوصفية للقفل
     *
     * @param int $elementId
     * @param string $lockKey
     * @param string $lockValue
     * @param array $shards
     */
    private function storeLockMetadata(int $elementId, string $lockKey, string $lockValue, array $shards): void
    {
        $metadata = [
            'lock_key' => $lockKey,
            'lock_value' => $lockValue,
            'shards' => $shards,
            'acquired_at' => microtime(true),
        ];

        // تخزين في Redis لسرعة الوصول
        $primaryShard = $this->getShardForKey($elementId);
        $this->redisShards[$primaryShard]->hset(
            'seatlock:metadata',
            $elementId,
            json_encode($metadata)
        );
    }

    /**
     * الحصول على البيانات الوصفية للقفل
     *
     * @param int $elementId
     * @return array|null
     */
    private function getLockMetadata(int $elementId): ?array
    {
        $primaryShard = $this->getShardForKey($elementId);
        $metadata = $this->redisShards[$primaryShard]->hget('seatlock:metadata', $elementId);

        return $metadata ? json_decode($metadata, true) : null;
    }

    /**
     * حذف البيانات الوصفية للقفل
     *
     * @param int $elementId
     */
    private function deleteLockMetadata(int $elementId): void
    {
        $primaryShard = $this->getShardForKey($elementId);
        $this->redisShards[$primaryShard]->hdel('seatlock:metadata', $elementId);
    }

    /**
     * تحرير الأقفال من عدة شريحات
     *
     * @param int $elementId
     * @param array $shards
     */
    private function releaseLockFromShards(int $elementId, array $shards): void
    {
        foreach ($shards as $shardName) {
            try {
                $this->redisShards[$shardName]->del($this->getLockKey($elementId));
            } catch (Exception $e) {
                Log::warning("Failed to release lock during cleanup", [
                    'shard' => $shardName,
                    'element_id' => $elementId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * الحصول على مفتاح القفل
     *
     * @param int $elementId
     * @return string
     */
    private function getLockKey(int $elementId): string
    {
        return "seatlock:{$elementId}";
    }

    /**
     * الحصول على الشريحة المناسبة لعنصر معين باستخدام التجزئة المتسقة
     *
     * @param int $elementId
     * @return string
     */
    private function getShardForKey(int $elementId): string
    {
        $hash = crc32((string) $elementId);
        $shardIndex = $hash % count($this->redisShards);
        return array_keys($this->redisShards)[$shardIndex];
    }

    /**
     * التحقق مما إذا كان الاستثناء ناتجاً عن توقف ميت (Deadlock)
     *
     * @param Exception $e
     * @return bool
     */
    private function isDeadlock(Exception $e): bool
    {
        $message = $e->getMessage();
        $code = $e->getCode();

        // أكواد توقف ميت شائعة في MySQL
        return strpos($message, 'Deadlock') !== false ||
               $code === 1213 || // Deadlock found
               $code === 1205;   // Lock wait timeout
    }
}
