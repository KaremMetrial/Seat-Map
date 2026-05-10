/**
 * 3D Seat Map Engine - Enhanced with Touch-Friendly Controls
 * 
 * Features:
 * - Touch-optimized control buttons (44px minimum)
 * - Smooth focus transitions (Kinesthetic UX)
 * - Responsive design for all screen sizes
 * - High contrast and reduced motion support
 */

class SeatMap3DEngine {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('Container element not found:', containerId);
            return;
        }

        this.options = {
            enableShadows: true,
            antialias: true,
            backgroundColor: 0xf0f0f0,
            seatHeight: 1.2,
            seatDepth: 0.8,
            enableTouchControls: true,
            ...options
        };
        
        // Three.js core components
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.controls = null;
        this.raycaster = new THREE.Raycaster();
        this.mouse = new THREE.Vector2();
        
        // Data and state
        this.seats = new Map();
        this.selectedSeats = new Set();
        this.hoveredSeat = null;
        
        // Control UI elements
        this.controlButtons = new Map();
        
        this.init();
    }
    
    init() {
        this.setupScene();
        this.setupCamera();
        this.setupRenderer();
        this.setupControls();
        this.setupLighting();
        this.setupEventListeners();
        this.createTouchControls(); // NEW: Touch-friendly controls
        this.animate();
    }
    
    setupScene() {
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(this.options.backgroundColor);
        this.scene.fog = new THREE.Fog(0xf0f0f0, 20, 100);
    }
    
    setupCamera() {
        this.camera = new THREE.PerspectiveCamera(
            60, 
            this.container.clientWidth / this.container.clientHeight, 
            0.1, 
            1000
        );
        this.camera.position.set(0, 15, 25);
    }
    
    setupRenderer() {
        this.renderer = new THREE.WebGLRenderer({ 
            antialias: this.options.antialias,
            canvas: document.createElement('canvas')
        });
        this.renderer.setSize(
            this.container.clientWidth, 
            this.container.clientHeight
        );
        this.renderer.shadowMap.enabled = this.options.enableShadows;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.domElement.style.borderRadius = '8px';
        this.container.appendChild(this.renderer.domElement);
    }
    
    setupControls() {
        this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.05;
        this.controls.minDistance = 10;
        this.controls.maxDistance = 50;
        this.controls.maxPolarAngle = Math.PI / 2.1; // Slightly above horizontal
        this.controls.enablePan = true;
        this.controls.enableZoom = true;
        this.controls.enableRotate = true;
    }
    
    setupLighting() {
        // Ambient light
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
        this.scene.add(ambientLight);
        
        // Main directional light (shadows)
        const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
        directionalLight.position.set(10, 20, 5);
        directionalLight.castShadow = true;
        directionalLight.shadow.mapSize.width = 2048;
        directionalLight.shadow.mapSize.height = 2048;
        directionalLight.shadow.camera.near = 0.5;
        directionalLight.shadow.camera.far = 50;
        directionalLight.shadow.camera.left = -30;
        directionalLight.shadow.camera.right = 30;
        directionalLight.shadow.camera.top = 30;
        directionalLight.shadow.camera.bottom = -30;
        this.scene.add(directionalLight);
        
        // Fill light
        const fillLight = new THREE.DirectionalLight(0xffffff, 0.3);
        fillLight.position.set(-10, 5, -10);
        this.scene.add(fillLight);
        
        // Stage spotlight
        const stageLight = new THREE.PointLight(0xffd700, 1, 50);
        stageLight.position.set(0, 5, -10);
        this.scene.add(stageLight);
    }
    
    // ============================================
    // NEW: TOUCH-FRIENDLY CONTROL BUTTONS
    // ============================================
    
    createTouchControls() {
        if (!this.options.enableTouchControls) return;
        
        const controlsContainer = document.createElement('div');
        controlsContainer.className = 'seatmap-3d-controls';
        controlsContainer.setAttribute('role', 'toolbar');
        controlsContainer.setAttribute('aria-label', '3D View Controls');
        
        // Control buttons configuration
        const controls = [
            {
                id: 'zoom-in',
                icon: this.createSVGIcon('plus'),
                label: 'Zoom In',
                action: () => this.zoomIn(),
                key: '=',
                large: true
            },
            {
                id: 'zoom-out',
                icon: this.createSVGIcon('minus'),
                label: 'Zoom Out',
                action: () => this.zoomOut(),
                key: '-',
                large: true
            },
            {
                id: 'reset-view',
                icon: this.createSVGIcon('home'),
                label: 'Reset View',
                action: () => this.resetCamera(),
                key: 'r'
            },
            {
                id: 'toggle-rotate',
                icon: this.createSVGIcon('rotate'),
                label: 'Toggle Rotation',
                action: () => this.toggleRotation(),
                key: 't'
            },
            {
                id: 'top-view',
                icon: this.createSVGIcon('eye'),
                label: 'Top View',
                action: () => this.setTopView(),
                key: 'v'
            }
        ];
        
        controls.forEach(control => {
            const button = this.createControlButton(control);
            controlsContainer.appendChild(button);
            this.controlButtons.set(control.id, button);
        });
        
        this.container.appendChild(controlsContainer);
        
        // Add keyboard shortcuts info tooltip
        this.addKeyboardShortcutsInfo();
    }
    
    createControlButton(config) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `seatmap-control-btn ${config.large ? 'large-touch' : ''}`;
        button.innerHTML = config.icon;
        button.setAttribute('aria-label', config.label);
        button.setAttribute('title', `${config.label} (${config.key})`);
        button.dataset.action = config.id;
        
        // Smooth interaction feedback
        button.addEventListener('mousedown', () => {
            button.style.transform = 'scale(0.95)';
        });
        
        button.addEventListener('mouseup', () => {
            button.style.transform = '';
        });
        
        button.addEventListener('mouseleave', () => {
            button.style.transform = '';
        });
        
        // Touch events
        button.addEventListener('touchstart', (e) => {
            e.preventDefault();
            button.style.transform = 'scale(0.95)';
        }, { passive: false });
        
        button.addEventListener('touchend', () => {
            button.style.transform = '';
        });
        
        // Click action
        button.addEventListener('click', (e) => {
            e.preventDefault();
            config.action();
            this.announceAction(config.label);
        });
        
        // Keyboard support
        button.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                config.action();
                this.announceAction(config.label);
            }
        });
        
        return button;
    }
    
    createSVGIcon(type) {
        const icons = {
            plus: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>`,
            minus: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>`,
            home: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>`,
            rotate: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"></path>
                <path d="M21 3v5h-5"></path>
            </svg>`,
            eye: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>`
        };
        return icons[type] || icons.plus;
    }
    
    addKeyboardShortcutsInfo() {
        const info = document.createElement('div');
        info.className = 'keyboard-shortcuts-info';
        info.style.cssText = `
            position: absolute;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 99;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            white-space: nowrap;
        `;
        info.textContent = 'Keyboard: Arrows, +/-, R, T, V';
        this.container.appendChild(info);
        
        // Show on focus
        this.container.addEventListener('focusin', () => {
            info.style.opacity = '1';
        });
        
        this.container.addEventListener('focusout', (e) => {
            if (!this.container.contains(e.relatedTarget)) {
                setTimeout(() => {
                    info.style.opacity = '0';
                }, 1000);
            }
        });
    }
    
    announceAction(action) {
        // Create or update ARIA live region
        let liveRegion = document.getElementById('seatmap-3d-announcer');
        if (!liveRegion) {
            liveRegion = document.createElement('div');
            liveRegion.id = 'seatmap-3d-announcer';
            liveRegion.setAttribute('aria-live', 'polite');
            liveRegion.setAttribute('aria-atomic', 'true');
            liveRegion.className = 'sr-only';
            document.body.appendChild(liveRegion);
        }
        liveRegion.textContent = `Action: ${action}`;
    }
    
    // ============================================
    // CONTROL ACTIONS
    // ============================================
    
    zoomIn() {
        this.controls.dollyIn(1.2);
        this.controls.update();
    }
    
    zoomOut() {
        this.controls.dollyOut(1.2);
        this.controls.update();
    }
    
    resetCamera() {
        // Smooth reset animation
        const startPos = this.camera.position.clone();
        const startTarget = this.controls.target.clone();
        const endPos = new THREE.Vector3(0, 15, 25);
        const endTarget = new THREE.Vector3(0, 0, 0);
        
        const duration = 500;
        const startTime = Date.now();
        
        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 3); // easeOutCubic
            
            this.camera.position.lerpVectors(startPos, endPos, ease);
            this.controls.target.lerpVectors(startTarget, endTarget, ease);
            this.controls.update();
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        animate();
    }
    
    toggleRotation() {
        this.controls.autoRotate = !this.controls.autoRotate;
        const button = this.controlButtons.get('toggle-rotate');
        if (button) {
            button.classList.toggle('active', this.controls.autoRotate);
            button.style.background = this.controls.autoRotate ? 'var(--seat-available)' : '';
            button.style.color = this.controls.autoRotate ? '#fff' : '';
        }
    }
    
    setTopView() {
        this.camera.position.set(0, 30, 0.001);
        this.controls.target.set(0, 0, 0);
        this.controls.update();
    }
    
    // ============================================
    // EVENT LISTENERS
    // ============================================
    
    setupEventListeners() {
        window.addEventListener('resize', () => this.onWindowResize(), false);
        this.renderer.domElement.addEventListener('click', (e) => this.onMouseClick(e), false);
        this.renderer.domElement.addEventListener('mousemove', (e) => this.onMouseMove(e), false);
        window.addEventListener('keydown', (e) => this.onKeyDown(e), false);
        
        // Touch support
        this.renderer.domElement.addEventListener('touchstart', (e) => this.onTouchStart(e), { passive: false });
        this.renderer.domElement.addEventListener('touchend', (e) => this.onTouchEnd(e), false);
    }
    
    onWindowResize() {
        this.camera.aspect = this.container.clientWidth / this.container.clientHeight;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(
            this.container.clientWidth, 
            this.container.clientHeight
        );
    }
    
    onMouseClick(event) {
        this.updateMouseCoords(event);
        this.raycaster.setFromCamera(this.mouse, this.camera);
        
        const intersects = this.raycaster.intersectObjects(
            Array.from(this.seats.values()),
            true
        );
        
        if (intersects.length > 0) {
            const clickedObject = intersects[0].object;
            const seatGroup = this.findSeatGroup(clickedObject);
            if (seatGroup && seatGroup.userData.status === 'available') {
                this.handleSeatSelection(seatGroup.userData);
            }
        }
    }
    
    onMouseMove(event) {
        this.updateMouseCoords(event);
        this.raycaster.setFromCamera(this.mouse, this.camera);
        
        const intersects = this.raycaster.intersectObjects(
            Array.from(this.seats.values()),
            true
        );
        
        // Hover effect
        if (intersects.length > 0) {
            const object = intersects[0].object;
            const seatGroup = this.findSeatGroup(object);
            
            if (seatGroup && seatGroup.userData.status === 'available') {
                if (this.hoveredSeat !== seatGroup) {
                    this.clearHoverEffect();
                    this.applyHoverEffect(seatGroup);
                    this.hoveredSeat = seatGroup;
                    this.renderer.domElement.style.cursor = 'pointer';
                }
                return;
            }
        }
        
        this.clearHoverEffect();
        this.renderer.domElement.style.cursor = 'default';
    }
    
    onTouchStart(event) {
        if (event.touches.length === 1) {
            // Single touch - could be tap or pan
            this.touchStartTime = Date.now();
            this.touchStartX = event.touches[0].clientX;
            this.touchStartY = event.touches[0].clientY;
        }
    }
    
    onTouchEnd(event) {
        if (!this.touchStartTime) return;
        
        const touchDuration = Date.now() - this.touchStartTime;
        const touchEndX = event.changedTouches[0].clientX;
        const touchEndY = event.changedTouches[0].clientY;
        
        const deltaX = Math.abs(touchEndX - this.touchStartX);
        const deltaY = Math.abs(touchEndY - this.touchStartY);
        
        // If it's a quick tap (not a swipe/pan), treat as click
        if (touchDuration < 300 && deltaX < 10 && deltaY < 10) {
            // Simulate click at touch position
            const rect = this.renderer.domElement.getBoundingClientRect();
            const simulatedEvent = {
                clientX: touchEndX,
                clientY: touchEndY,
                target: this.renderer.domElement
            };
            this.onMouseClick(simulatedEvent);
        }
        
        this.touchStartTime = null;
    }
    
    onKeyDown(event) {
        const key = event.key.toLowerCase();
        let handled = true;
        
        switch (key) {
            case 'arrowup':
                this.camera.position.z -= 1;
                break;
            case 'arrowdown':
                this.camera.position.z += 1;
                break;
            case 'arrowleft':
                this.camera.position.x -= 1;
                break;
            case 'arrowright':
                this.camera.position.x += 1;
                break;
            case '+':
            case '=':
                this.zoomIn();
                break;
            case '-':
            case '_':
                this.zoomOut();
                break;
            case 'r':
                this.resetCamera();
                break;
            case 't':
                this.toggleRotation();
                break;
            case 'v':
                this.setTopView();
                break;
            case '0':
                this.controls.reset();
                break;
            default:
                handled = false;
        }
        
        if (handled) {
            event.preventDefault();
            this.controls.update();
        }
    }
    
    // ============================================
    // HELPER METHODS
    // ============================================
    
    updateMouseCoords(event) {
        const rect = this.renderer.domElement.getBoundingClientRect();
        this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
    }
    
    findSeatGroup(object) {
        let current = object;
        while (current && current !== this.scene) {
            if (current.userData && current.userData.type === 'seat') {
                return current;
            }
            current = current.parent;
        }
        return null;
    }
    
    applyHoverEffect(seatGroup) {
        seatGroup.scale.setScalar(1.08);
        // Add glow effect
        const glowGeometry = new THREE.RingGeometry(0.3, 0.5, 32);
        const glowMaterial = new THREE.MeshBasicMaterial({ 
            color: 0xffff00, 
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.6
        });
        const glow = new THREE.Mesh(glowGeometry, glowMaterial);
        glow.rotation.x = -Math.PI / 2;
        glow.position.y = 0.01;
        glow.name = 'hoverGlow';
        seatGroup.add(glow);
    }
    
    clearHoverEffect() {
        if (this.hoveredSeat) {
            this.hoveredSeat.scale.setScalar(1);
            const glow = this.hoveredSeat.getObjectByName('hoverGlow');
            if (glow) {
                this.hoveredSeat.remove(glow);
            }
            this.hoveredSeat = null;
        }
    }
    
    handleSeatSelection(seatData) {
        // This should be overridden by the parent application
        console.log('Seat selected:', seatData);
        if (this.onSeatClick) {
            this.onSeatClick(seatData);
        }
    }
    
    // ============================================
    // PUBLIC API
    // ============================================
    
    addSeat(seatData) {
        const seatGroup = this.createSeatMesh(seatData);
        this.seats.set(seatData.id, seatGroup);
        this.scene.add(seatGroup);
        return seatGroup;
    }
    
    createSeatMesh(seatData) {
        const group = new THREE.Group();
        group.userData = { ...seatData, type: 'seat' };
        
        const width = seatData.width || 0.6;
        const depth = seatData.depth || 0.8;
        const height = this.options.seatHeight;
        
        // Materials based on status
        const materials = this.getSeatMaterials(seatData.status);
        
        // Seat cushion
        const seatGeom = new THREE.BoxGeometry(width, height * 0.4, depth);
        const seatMesh = new THREE.Mesh(seatGeom, materials.body);
        seatMesh.position.y = height * 0.2;
        seatMesh.castShadow = true;
        seatMesh.receiveShadow = true;
        group.add(seatMesh);
        
        // Backrest
        const backGeom = new THREE.BoxGeometry(width, height * 0.6, 0.05);
        const backMesh = new THREE.Mesh(backGeom, materials.body);
        backMesh.position.set(0, height * 0.5, -depth * 0.5 + 0.025);
        backMesh.castShadow = true;
        group.add(backMesh);
        
        // Label
        if (seatData.label) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = 128;
            canvas.height = 64;
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 24px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(seatData.label, 64, 32);
            
            const texture = new THREE.CanvasTexture(canvas);
            const spriteMaterial = new THREE.SpriteMaterial({ map: texture });
            const sprite = new THREE.Sprite(spriteMaterial);
            sprite.scale.set(0.5, 0.25, 1);
            sprite.position.set(0, height + 0.1, 0);
            group.add(sprite);
        }
        
        group.position.set(seatData.x, 0, -seatData.y);
        
        return group;
    }
    
    getSeatMaterials(status) {
        const colors = {
            available: 0x0056b3,
            locked: 0xd4a017,
            booked: 0xa94442,
            selected: 0x28a745
        };
        
        const color = colors[status] || colors.available;
        
        return {
            body: new THREE.MeshStandardMaterial({ 
                color: color,
                metalness: 0.2,
                roughness: 0.7
            })
        };
    }
    
    updateSeatStatus(seatId, status) {
        const seat = this.seats.get(seatId);
        if (!seat) return;
        
        seat.userData.status = status;
        const materials = this.getSeatMaterials(status);
        
        seat.traverse((child) => {
            if (child.isMesh && child.material) {
                child.material = materials.body;
            }
        });
    }
    
    animate() {
        requestAnimationFrame(() => this.animate());
        
        this.controls.update();
        this.renderer.render(this.scene, this.camera);
    }
    
    destroy() {
        window.removeEventListener('resize', this.onWindowResize);
        this.renderer.dispose();
        this.renderer.forceContextLoss();
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SeatMap3DEngine;
}
