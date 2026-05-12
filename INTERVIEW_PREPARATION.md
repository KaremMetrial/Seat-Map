# SeatMap Implementation - Executive Interview Preparation
**Senior Solutions Architect Discovery Session**  
**Date: 2026-05-12**

---

## Executive Summary

This document provides a comprehensive set of interview questions for a high-stakes customer engagement regarding the SeatMap implementation. Each question is categorized by domain and includes expert rationale to guide the conversation with authority and technical depth.

---

## 1. TECHNICAL ARCHITECTURE & INTEGRATION

### 1.1 API Latency & Performance

| Question | Expert Rationale |
|----------|------------------|
| **"What is your target API response time for seat map rendering, and how do you plan to handle viewport-based culling for large venues (50,000+ seats)?"** | Critical because the API supports `x, y, width, height` viewport parameters to limit data transfer. A "red flag" answer would indicate no performance optimization strategy or unrealistic latency expectations (<50ms for large datasets). |
| **"How do you measure and monitor API latency in production, and what are your SLA commitments for 95th percentile response times?"** | The codebase shows `throttle:bookings` middleware, indicating awareness of rate limiting. A weak answer suggests lack of observability. |
| **"Do you have CDN caching strategy for static assets (canvas background, zone configurations) and dynamic seat availability?"** | The `background_image`, `background_color`, and `canvas_width/height` are served per-event. Red flag: no distinction between static and volatile data. |

### 1.2 Real-Time Synchronization

| Question | Expert Rationale |
|----------|------------------|
| **"How do you handle real-time seat availability synchronization between multiple concurrent users?"** | The system uses a 10-minute lock with `SELECT FOR UPDATE` and unique constraints. A poor answer would suggest polling or no conflict resolution. |
| **"What WebSocket or Server-Sent Events infrastructure do you have for push notifications when seats are locked/blocked by other users?"** | The API endpoints are REST-only. Red flag: no real-time sync means users see stale availability until refresh. |
| **"Describe the frequency and mechanism of seat status polling in the frontend. Do you implement exponential backoff?"** | Without knowing the frontend strategy, you cannot assess user experience quality. |

### 1.3 Concurrency & Double-Booking Prevention

| Question | Expert Rationale |
|----------|------------------|
| **"Walk me through your deadlock handling strategy. How many retries do you attempt, and what backoff algorithm do you use?"** | The code shows `MAX_DEADLOCK_RETRIES = 3` with exponential backoff (100ms, 200ms, 400ms). Red flag: no mention of these specific mechanisms. |
| **"Explain the database-level unique constraint on `element_locks(event_element_id)` and how it serves as your final defense against race conditions."** | This is explicitly documented in the migration. Missing this detail indicates poor architecture review. |
| **"How do you handle the scenario where a user's browser crashes after locking seats but before confirming payment?"** | The 10-minute TTL with automatic expiration is the answer. Red flag: no automated cleanup or monitoring. |

### 1.4 Data Mapping & Consistency

| Question | Expert Rationale |
|----------|------------------|
| **"How do you ensure data consistency between TemplateElement (master layout) and EventElement (point-in-time snapshot)?"** | The codebase explicitly states `EventElement` is a SNAPSHOT that NEVER changes after creation. Red flag: any process that mutates EventElement post-publish. |
| **"What happens to booked seats if venue layout changes after an event is published?"** | Snapshot architecture prevents this issue. Red flag: suggesting layout changes affect existing bookings. |
| **"How do zone-based pricing modifiers work, and where are they stored?"** | The `element_zone_map` pivot table stores `price_modifier` and `modifier_type`. Red flag: pricing stored on elements rather than zones. |

---

## 2. USER EXPERIENCE (UX) & FRONTEND REQUIREMENTS

### 2.1 Seat Selection Logic

| Question | Expert Rationale |
|----------|------------------|
| **"Describe the seat selection flow for 'Choose My Seats' vs 'Find My Seats'. What are the key UX differences?"** | Deliverable #1 is manual selection, #2 is auto-assignment. Red flag: inability to articulate the distinction. |
| **"How do you handle the 'isolated seats' validation? Is it configurable per event?"** | The requirement shows a checkbox for this validation. Red flag: no toggle or hardcoded behavior. |
| **"What client-side validation do you implement before submitting seat selections to the API?"** | Understanding this reveals frontend sophistication. Red flag: relying solely on server validation. |

### 2.2 Visual Representation & Zoom/Pan

| Question | Expert Rationale |
|----------|------------------|
| **"How do you implement the zoom functionality? Do you use SVG transforms or canvas scaling?"** | The spec mentions plus/minus buttons, mouse wheel, and pinch-to-zoom. Red flag: no mobile gesture support. |
| **"Describe your mini-map implementation. How do you keep the viewport indicator synchronized?"** | Required for Phase 2. Red flag: no mention of spatial awareness aids. |
| **"How do you handle seat icons (POD, Reduced Visibility) in the rendering pipeline?"** | The `data_json` field stores these attributes. Red flag: hardcoded icon logic. |

### 2.3 Device Responsiveness

| Question | Expert Rationale |
|----------|------------------|
| **"How does the layout adapt between mobile (50% map height) and desktop (side-by-side) views?"** | The spec explicitly defines these ratios. Red flag: CSS-only solution without viewport consideration. |
| **"What touch interactions are supported beyond pinch-to-zoom? (double-tap, swipe, etc.)"** | Basic accessibility. Red flag: mouse-only interactions. |

### 2.4 Dynamic Seat Changes

| Question | Expert Rationale |
|----------|------------------|
| **"When a user modifies seat quantity, do you requery availability or use cached data?"** | Critical for preventing overselling. Red flag: no revalidation. |
| **"How do you handle the scenario where a user has seats locked but navigates away and returns within the lock window?"** | The `lock_key` must be persisted (localStorage/sessionStorage). Red flag: requiring re-selection. |

---

## 3. BUSINESS LOGIC & RULES ENGINE

### 3.1 Pricing Tiers & Seat Categories

| Question | Expert Rationale |
|----------|------------------|
| **"How are seat categories (VIP, extra legroom, reduced visibility) defined and managed?"** | The `data_json` field stores these. Red flag: no structured categorization system. |
| **"Explain your zone-based pricing model. Can a single zone have multiple price points?"** | The `price_modifier` on `element_zone_map`. Red flag: flat pricing per zone only. |
| **"How do you handle dynamic pricing or time-based price adjustments?"** | The `PricingService` is mentioned but not detailed. Red flag: no pricing service integration. |

### 3.2 Grouping/Blocking Seats

| Question | Expert Rationale |
|----------|------------------|
| **"Can administrators block or hold specific seats for VIPs or press? How is this implemented?"** | The lock mechanism exists but is user-centric. Red flag: no admin override. |
| **"Is there a concept of seat groups (e.g., 'Family Section A') that can be sold together?"** | Not explicitly mentioned. Red flag: no grouping logic. |

### 3.3 Hold Durations & Session Timeouts

| Question | Expert Rationale |
|----------|------------------|
| **"The default lock duration is 10 minutes. Can this be customized per event or venue?"** | Hardcoded in `SeatLockService::LOCK_TTL_MINUTES`. Red flag: no configurability. |
| **"How do you communicate remaining lock time to users? Is there a visual countdown?"** | Essential UX. Red flag: no timeout awareness. |
| **"What happens when a lock expires? Are users notified, or do they discover it at checkout?"** | The `expires_at` field exists. Red flag: silent failure. |

### 3.4 Complex Inventory Rules

| Question | Expert Rationale |
|----------|------------------|
| **"Can you enforce minimum/maximum purchase limits per user or per transaction?"** | Not implemented. Red flag: no quantity restrictions. |
| **"How do you handle overselling at the zone level vs. individual seat level?"** | The system locks individual elements. Red flag: zone-level overselling possible. |

---

## 4. EDGE CASES & EXCEPTION HANDLING

### 4.1 Cancellations & Refunds

| Question | Expert Rationale |
|----------|------------------|
| **"Describe the full cancellation flow. What happens to locked seats when a booking is cancelled?"** | `Booking::cancel()` deletes locks and updates capacity. Red flag: orphaned locks. |
| **"Can users cancel individual seats from a multi-seat booking?"** | `BookingItem` supports per-item status. Red flag: only full booking cancellation. |
| **"How do partial refunds work? Is there pro-rated pricing logic?"** | Not addressed. Red flag: no refund calculation. |

### 4.2 Seat Upgrades/Downgrades

| Question | Expert Rationale |
|----------|------------------|
| **"Is seat upgrading supported? If a user wants to move from a standard seat to VIP mid-transaction, how is this handled?"** | Not implemented. Red flag: no upgrade path. |
| **"What happens to the original seat's lock when upgrading?"** | Critical for concurrency. Red flag: no lock transfer. |

### 4.3 API Downtime & Desynchronization

| Question | Expert Rationale |
|----------|------------------|
| **"How do you handle the scenario where the booking API is unavailable during checkout?"** | The frontend must have retry logic. Red flag: no fallback. |
| **"What client-side caching strategy do you use for seat availability? How do you detect server/client divergence?"** | Essential for resilience. Red flag: no cache invalidation. |
| **"Do you implement circuit breaker patterns for downstream service failures?"** | Advanced resilience pattern. Red flag: no fault tolerance. |

---

## 5. SCALABILITY & PERFORMANCE

### 5.1 Peak Load Handling

| Question | Expert Rationale |
|----------|------------------|
| **"What is your expected concurrent user load during high-demand sales (e.g., ticket launches)?"** | The booking endpoints have `throttle:bookings` middleware. Red flag: no load testing data. |
| **"How do you handle flash sales or very high traffic events?"** | The architecture supports horizontal scaling. Red flag: no auto-scaling. |
| **"Do you use read replicas for seat availability queries?"** | Not mentioned. Red flag: no database scaling strategy. |

### 5.2 Caching Strategies

| Question | Expert Rationale |
|----------|------------------|
| **"What caching layers do you implement? (Redis, Memcached, CDN)"** | Not specified. Red flag: no caching strategy. |
| **"How long do you cache seat availability data, and how do you invalidate it?"** | Critical for consistency. Red flag: no cache invalidation. |

### 5.3 Global Latency Considerations

| Question | Expert Rationale |
|----------|------------------|
| **"Do you have multi-region deployment strategy for global audiences?"** | Not mentioned. Red flag: single-region only. |
| **"How do you handle latency for users geographically distant from your servers?"** | The viewport culling helps. Red flag: no edge computing. |

---

## Interview Success Criteria

| Category | Green Flags | Red Flags |
|----------|-------------|-----------|
| **Concurrency** | Mentions deadlocks, retries, unique constraints, SELECT FOR UPDATE | Relies on application-level checks only |
| **UX** | Describes zoom/pan, mini-map, countdown timers, responsive design | Mouse-only, no mobile gestures, no timeout awareness |
| **Business Logic** | Configurable TTL, per-zone pricing, admin overrides | Hardcoded values, flat pricing, no admin tools |
| **Edge Cases** | Handles cancellation, refund, upgrade scenarios | Silent failures, no fallback mechanisms |
| **Scalability** | Mentions caching, rate limiting, load testing | No performance considerations |

---

## Appendix: Key Architecture Diagrams

### Booking Flow Sequence
```
1. Frontend → GET /events/{id}/seatmap (viewport params)
2. Frontend → POST /bookings/lock (element_ids, lock_key)
3. Frontend → POST /bookings (customer details, lock_key)
4. Payment Gateway → POST /bookings/{ref}/confirm
5. On Cancel → DELETE /bookings/{ref}
```

### Data Model Relationships
```
VenueTemplate
  └─ TemplateZone (price ranges, categories)
  └─ TemplateElement (master layout)
        └─ EventElement (snapshot per event)
              └─ BookingItem (actual booking)
```

---

*Prepared by: Senior Solutions Architect*  
*Version: 1.0*

---

## IMPLEMENTATION GAP ANALYSIS

### Requirements vs Actual Implementation

| **Requirement** | **Status** | **Details** |
|-----------------|------------|-------------|
| **Deliverable #1 - Choose My Seats** | ✅ **PARTIALLY IMPLEMENTED** | Basic seat selection exists but missing: quantity modification controls, cart module, flow mode panel, filters module |
| **Deliverable #2 - Find My Seats** | ❌ **NOT IMPLEMENTED** | Auto-assignment functionality is absent. No zone list panel, no quantity input, no auto-seat selection |
| **Deliverable #3 - Flow Mode Panel** | ❌ **NOT IMPLEMENTED** | No UI component to switch between Choose/Find flows |
| **Deliverable #4 - Filters Module** | ❌ **NOT IMPLEMENTED** | No filter endpoints or UI. Missing: price range slider, location filter, availability filter, amenities filter, view filter, group size filter, seat type filter |
| **Deliverable #5 - Zones Grouping by Price** | ❌ **NOT IMPLEMENTED** | No price-based zone grouping UI or API endpoint |
| **Deliverable #6 - 2D Map Plugin** | ✅ **PARTIALLY IMPLEMENTED** | Basic map rendering exists but missing: top view with popups, mini-map, legend, isolated seats validation, zone highlighting |

### Critical Missing Features

#### 1. **Frontend Flow Modes**
- **Required**: Toggle between "Choose My Seats" (manual) and "Find My Seats" (auto)
- **Actual**: Single hardcoded flow with no switching capability
- **Impact**: Cannot fulfill core deliverable requirements

#### 2. **Cart Module**
- **Required**: Minimized cart, show/hide details, remove individual seats, total calculation
- **Actual**: No cart state management
- **Impact**: Users cannot modify selections before checkout

#### 3. **Filter System**
- **Required**: 7 filter types with i18n support and apply/clear functionality
- **Actual**: No filtering endpoints or UI
- **Impact**: Users cannot refine seat selection by price, location, amenities

#### 4. **Non-Seated Sectors**
- **Required**: Popup for quantity input when clicking non-bookable sectors
- **Actual**: Not implemented
- **Impact**: Cannot handle general admission or standing areas

#### 5. **Isolated Seats Validation**
- **Required**: Prevent single empty seats with configurable toggle
- **Actual**: No validation logic
- **Impact**: Potential for poor user experience

### API Endpoints Present vs Required

| **Endpoint** | **Present** | **Purpose** |
|--------------|-------------|-------------|
| `GET /api/v1/events/{event}/seatmap` | ✅ | Full seat map |
| `GET /api/v1/events/{event}/available` | ✅ | Available seats only |
| `POST /api/v1/bookings/lock` | ✅ | Lock seats |
| `POST /api/v1/bookings` | ✅ | Create booking |
| `POST /api/v1/bookings/{ref}/confirm` | ✅ | Confirm booking |
| `DELETE /api/v1/bookings/{ref}` | ✅ | Cancel booking |
| `GET /api/v1/events/{event}/filters` | ❌ | **MISSING** - Filter options |
| `GET /api/v1/events/{event}/zones/prices` | ❌ | **MISSING** - Price groupings |
| `POST /api/v1/bookings/auto-assign` | ❌ | **MISSING** - Auto seat selection |

### Data Model Gaps

| **Feature** | **Model** | **Status** | **Missing Fields** |
|-------------|-----------|------------|-------------------|
| Seat Categories | `data_json` | ⚠️ Basic | `seat_category` (VIP, standard, etc.), `visibility` (normal, reduced), `accessibility` |
| Zone Pricing | `TemplateZone` | ✅ | `price_tier` for grouping |
| Lock Management | `ElementLock` | ✅ | `session_id` for multi-element grouping |
| Booking Items | `BookingItem` | ✅ | `upgrade_from_item_id` for seat changes |

### Concurrency & Locking Analysis

| **Aspect** | **Implementation** | **Gap** |
|------------|-------------------|---------|
| Lock Duration | 10 minutes hardcoded | No per-event TTL configuration |
| Deadlock Handling | 3 retries with backoff | No monitoring/metrics |
| Lock Extension | Not implemented | Cannot extend user session |
| Lock Release | On confirm/cancel only | No explicit release endpoint |

### Performance Considerations

| **Optimization** | **Implemented** | **Recommendation** |
|------------------|-----------------|-------------------|
| Viewport Culling | ✅ | Add client-side zoom level thresholds |
| Batch Hydration | ✅ | Add Redis caching layer |
| N+1 Prevention | ✅ | Add query result caching |
| Rate Limiting | ✅ | Add per-user/per-IP limits |

---

## INTERVIEW DISCUSSION PROMPTS

### Opening Questions
1. "Based on my analysis, I see the backend booking and locking infrastructure is well-designed. However, several key frontend deliverables are not yet implemented. How is the team prioritizing these features?"

2. "The requirements mention a 'flow mode panel' to switch between manual and auto seat selection. What's the current development status and timeline for this?"

3. "I noticed the filter system is not implemented. Are there plans to integrate with a specific frontend framework, or is this a backend responsibility?"

### Technical Deep-Dive Questions
4. "For the 'Find My Seats' auto-assignment feature, what algorithm are you planning to use? (proximity-based, price-optimized, accessibility-prioritized?)"

5. "How will you handle the non-seated sector popup? Should this be a modal, a sidebar, or inline quantity selector?"

6. "For isolated seats validation, do you have a specific algorithm in mind? (checking adjacent seats in all 8 directions?)"

### Business Impact Questions
7. "What's the business impact of launching without the filter system? Will you need to simplify the user journey?"

8. "Are there any compliance requirements for displaying seat categories (VIP, reduced visibility, etc.)?"

9. "How do you plan to handle the mobile vs desktop layout differences mentioned in the requirements?"

### Risk Assessment Questions
10. "What's your rollback strategy if the 'Choose My Seats' flow causes performance issues during a high-traffic sale?"

11. "How will you monitor lock expiration rates and user drop-off at the payment stage?"

12. "What's your contingency plan if the auto-assignment algorithm produces suboptimal results?"

---

*Prepared by: Senior Solutions Architect*  
*Version: 1.1 (Updated with Gap Analysis)*
