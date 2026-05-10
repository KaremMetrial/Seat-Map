<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seatmap System Test Interface</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: #1e293b;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        h1 {
            font-size: 1.5rem;
            color: #f8fafc;
        }

        .badge {
            background: #3b82f6;
            color: white;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .card {
            background: #1e293b;
            border-radius: 12px;
            padding: 20px;
        }

        .card-header {
            font-size: 0.875rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 16px;
        }

        .btn {
            width: 100%;
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 8px;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #475569;
            color: #e2e8f0;
        }

        .btn-outline:hover {
            background: #334155;
        }

        select, input {
            width: 100%;
            padding: 10px 12px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 0.875rem;
            margin-bottom: 12px;
        }

        select:focus, input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        label {
            display: block;
            font-size: 0.75rem;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .canvas-container {
            background: #1e293b;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }

        #seatmap-canvas {
            width: 100%;
            height: 600px;
            cursor: crosshair;
        }

        .legend {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(15, 23, 42, 0.9);
            padding: 12px 16px;
            border-radius: 8px;
            display: flex;
            gap: 16px;
            font-size: 0.75rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        .legend-dot.available { background: #10b981; }
        .legend-dot.booked { background: #ef4444; }
        .legend-dot.locked { background: #f59e0b; }
        .legend-dot.selected { background: #3b82f6; }

        .status-bar {
            background: #1e293b;
            border-radius: 12px;
            padding: 16px 20px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-item {
            text-align: center;
        }

        .status-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #f8fafc;
        }

        .status-label {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .log-container {
            max-height: 200px;
            overflow-y: auto;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.75rem;
            background: #0f172a;
            border-radius: 8px;
            padding: 12px;
        }

        .log-entry {
            padding: 4px 0;
            border-bottom: 1px solid #1e293b;
        }

        .log-entry:last-child {
            border-bottom: none;
        }

        .log-time {
            color: #64748b;
        }

        .log-success { color: #10b981; }
        .log-error { color: #ef4444; }
        .log-info { color: #3b82f6; }

        .selected-seats {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 12px;
        }

        .selected-seat {
            background: #3b82f6;
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .selected-seat button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
        }

        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 16px;
        }

        .tab {
            padding: 8px 16px;
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .tab.active {
            background: #334155;
            color: #f8fafc;
        }

        .tab:hover {
            background: #334155;
        }

        .booking-summary {
            background: #0f172a;
            border-radius: 8px;
            padding: 12px;
            margin-top: 12px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 0.875rem;
        }

        .price-row.total {
            border-top: 1px solid #334155;
            margin-top: 8px;
            padding-top: 12px;
            font-weight: 600;
        }

        .hidden {
            display: none !important;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 0.875rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid #10b981;
            color: #10b981;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div>
                <h1>🎭 Seatmap System Test Interface</h1>
            </div>
            <span class="badge">API v1</span>
        </header>

        <div class="grid">
            <div class="sidebar">
                <!-- Tabs -->
                <div class="tabs" style="background: #1e293b; border-radius: 8px; padding: 4px; margin-bottom: 12px;">
                    <button class="tab active" onclick="switchTab('venues')">Venues</button>
                    <button class="tab" onclick="switchTab('templates')">Templates</button>
                    <button class="tab" onclick="switchTab('booking')">Booking</button>
                </div>

                <!-- Venues Tab -->
                <div id="tab-venues">
                    <div class="card">
                        <div class="card-header">🏟️ Venues</div>
                        <select id="venue-select">
                            <option value="">Select a venue...</option>
                        </select>
                        <button class="btn btn-outline" onclick="loadVenues()">Refresh Venues</button>
                        <button class="btn btn-primary" onclick="showCreateVenueModal()">+ Create Venue</button>
                    </div>

                    <div class="card">
                        <div class="card-header">📄 Templates</div>
                        <select id="template-select">
                            <option value="">Select a template...</option>
                        </select>
                        <button class="btn btn-outline" onclick="loadTemplates()">Refresh Templates</button>
                        <button class="btn btn-primary" onclick="showCreateTemplateModal()">+ Create Template</button>
                        <button class="btn btn-outline" onclick="duplicateTemplate()" id="dup-template-btn" disabled>Duplicate Template</button>
                    </div>

                    <div class="card">
                        <div class="card-header">📅 Events</div>
                        <select id="event-select">
                            <option value="">Select an event...</option>
                        </select>
                        <button class="btn btn-outline" onclick="loadEvents()">Refresh Events</button>
                        <button class="btn btn-primary" onclick="showCreateEventModal()">+ Create Event</button>
                        <button class="btn btn-success" id="publish-btn" onclick="publishEvent()" disabled>Publish Event</button>
                    </div>
                </div>

                <!-- Templates Tab -->
                <div id="tab-templates" class="hidden">
                    <div class="card">
                        <div class="card-header">🎨 Generate Seats</div>
                        <label>Start X Position</label>
                        <input type="number" id="gen-start-x" value="50">
                        <label>Start Y Position</label>
                        <input type="number" id="gen-start-y" value="50">
                        <label>Number of Rows</label>
                        <input type="number" id="gen-rows" value="10">
                        <label>Seats per Row</label>
                        <input type="number" id="gen-seats-per-row" value="20">
                        <label>Seat Width</label>
                        <input type="number" id="gen-seat-width" value="30">
                        <label>Seat Height</label>
                        <input type="number" id="gen-seat-height" value="25">
                        <label>Gap X</label>
                        <input type="number" id="gen-gap-x" value="5">
                        <label>Gap Y</label>
                        <input type="number" id="gen-gap-y" value="5">
                        <label>Zone (optional)</label>
                        <select id="gen-zone">
                            <option value="">No zone</option>
                        </select>
                        <label>Seat Type</label>
                        <select id="gen-seat-type">
                            <option value="regular">Regular</option>
                            <option value="vip">VIP</option>
                            <option value="wheelchair">Wheelchair</option>
                            <option value="companion">Companion</option>
                        </select>
                        <button class="btn btn-primary" onclick="generateSeats()">Generate Seats</button>
                    </div>

                    <div class="card">
                        <div class="card-header">🏷️ Zones</div>
                        <div id="zones-list"></div>
                        <button class="btn btn-outline" onclick="loadZones()">Refresh Zones</button>
                        <button class="btn btn-primary" onclick="showCreateZoneModal()">+ Create Zone</button>
                        <button class="btn btn-outline" onclick="createDefaultZones()">Create Default Zones</button>
                    </div>

                    <div class="card">
                        <div class="card-header">🧱 Add Element</div>
                        <select id="element-type">
                            <option value="seat">Seat</option>
                            <option value="table">Table</option>
                            <option value="stage">Stage</option>
                            <option value="entrance">Entrance</option>
                            <option value="aisle">Aisle</option>
                            <option value="standing_zone">Standing Zone</option>
                        </select>
                        <label>X Position</label>
                        <input type="number" id="el-x" value="100">
                        <label>Y Position</label>
                        <input type="number" id="el-y" value="100">
                        <label>Width</label>
                        <input type="number" id="el-width" value="40">
                        <label>Height</label>
                        <input type="number" id="el-height" value="40">
                        <label>Label</label>
                        <input type="text" id="el-label" placeholder="Element label">
                        <button class="btn btn-primary" onclick="addElement()">Add Element</button>
                    </div>
                </div>

                <!-- Booking Tab -->
                <div id="tab-booking" class="hidden">
                    <div class="card">
                        <div class="card-header">🎟️ Selected Seats</div>
                        <div id="selected-seats-container">
                            <div class="selected-seats" id="selected-seats"></div>
                        </div>
                        
                        <label>Customer Name</label>
                        <input type="text" id="customer-name" placeholder="John Doe">
                        <label>Customer Email</label>
                        <input type="email" id="customer-email" placeholder="john@example.com">
                        
                        <div class="booking-summary" id="booking-summary">
                            <div class="price-row">
                                <span>Subtotal:</span>
                                <span id="subtotal">$0.00</span>
                            </div>
                            <div class="price-row">
                                <span>Service Fee:</span>
                                <span id="service-fee">$0.00</span>
                            </div>
                            <div class="price-row">
                                <span>Tax:</span>
                                <span id="tax">$0.00</span>
                            </div>
                            <div class="price-row total">
                                <span>Total:</span>
                                <span id="total">$0.00</span>
                            </div>
                        </div>
                        
                        <button class="btn btn-success" id="book-btn" onclick="createBooking()" disabled>Book Selected Seats</button>
                        <button class="btn btn-outline" id="lock-btn" onclick="lockSeats()" disabled>Lock Seats (10 min)</button>
                    </div>
                </div>

                <!-- Actions Card -->
                <div class="card">
                    <div class="card-header">⚡ Actions</div>
                    <button class="btn btn-outline" onclick="clearSelection()">Clear Selection</button>
                    <button class="btn btn-outline" onclick="loadTemplateElements()">Refresh Elements</button>
                    <button class="btn btn-danger" onclick="deleteAllElements()">Delete All Elements</button>
                </div>
            </div>

            <div>
                <!-- Canvas -->
                <div class="canvas-container">
                    <canvas id="seatmap-canvas"></canvas>
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-dot available"></div>
                            <span>Available</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot selected"></div>
                            <span>Selected</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot locked"></div>
                            <span>Locked</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot booked"></div>
                            <span>Booked</span>
                        </div>
                    </div>
                </div>

                <!-- Status Bar -->
                <div class="status-bar">
                    <div class="status-item">
                        <div class="status-value" id="total-seats">0</div>
                        <div class="status-label">Total Seats</div>
                    </div>
                    <div class="status-item">
                        <div class="status-value" id="available-seats">0</div>
                        <div class="status-label">Available</div>
                    </div>
                    <div class="status-item">
                        <div class="status-value" id="locked-seats">0</div>
                        <div class="status-label">Locked</div>
                    </div>
                    <div class="status-item">
                        <div class="status-value" id="booked-seats">0</div>
                        <div class="status-label">Booked</div>
                    </div>
                </div>

                <!-- Log -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">📋 Activity Log</div>
                    <div class="log-container" id="log-container"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Venue Modal -->
    <div id="venue-modal" class="hidden" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 1000;">
        <div class="card" style="width: 400px;">
            <div class="card-header">Create Venue</div>
            <input type="text" id="venue-name" placeholder="Venue Name">
            <select id="venue-type">
                <option value="cinema">Cinema</option>
                <option value="stadium">Stadium</option>
                <option value="theater">Theater</option>
                <option value="custom">Custom</option>
            </select>
            <input type="number" id="venue-width" placeholder="Canvas Width" value="800">
            <input type="number" id="venue-height" placeholder="Canvas Height" value="600">
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-primary" onclick="createVenue()">Create</button>
                <button class="btn btn-outline" onclick="hideModal('venue-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Create Template Modal -->
    <div id="template-modal" class="hidden" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 1000;">
        <div class="card" style="width: 400px;">
            <div class="card-header">Create Template</div>
            <input type="text" id="template-name" placeholder="Template Name">
            <input type="number" id="template-width" placeholder="Canvas Width" value="800">
            <input type="number" id="template-height" placeholder="Canvas Height" value="600">
            <input type="color" id="template-bg-color" value="#1a1a2e" style="width: 100%; height: 40px; margin-bottom: 12px;">
            <label style="font-size: 0.75rem; color: #94a3b8;">Scale Factor (1 unit = X meters)</label>
            <input type="number" id="template-scale" placeholder="0.05" value="0.05" step="0.001">
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-primary" onclick="createTemplate()">Create</button>
                <button class="btn btn-outline" onclick="hideModal('template-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Create Zone Modal -->
    <div id="zone-modal" class="hidden" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 1000;">
        <div class="card" style="width: 400px;">
            <div class="card-header">Create Zone</div>
            <input type="text" id="zone-name" placeholder="Zone Name (e.g., VIP)">
            <input type="text" id="zone-code" placeholder="Code (e.g., VIP)" maxlength="10">
            <input type="color" id="zone-color" value="#ffd700" style="width: 100%; height: 40px; margin-bottom: 12px;">
            <label>Base Price Modifier ($)</label>
            <input type="number" id="zone-price" placeholder="0.00" value="0" step="0.01">
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-primary" onclick="createZone()">Create</button>
                <button class="btn btn-outline" onclick="hideModal('zone-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Create Event Modal -->
    <div id="event-modal" class="hidden" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 1000;">
        <div class="card" style="width: 400px;">
            <div class="card-header">Create Event</div>
            <input type="text" id="event-title" placeholder="Event Title">
            <input type="datetime-local" id="event-start">
            <input type="datetime-local" id="event-end">
            <input type="number" id="event-price" placeholder="Base Price" value="50" step="0.01">
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-primary" onclick="createEvent()">Create</button>
                <button class="btn btn-outline" onclick="hideModal('event-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // API Base URL
        const API_BASE = '/api/v1';

        // State
        let currentVenue = null;
        let currentTemplate = null;
        let currentEvent = null;
        let seatMapData = null;
        let templateElements = null;
        let zones = [];
        let selectedElements = new Set();
        let canvas, ctx;
        let scale = 1;
        let offsetX = 0, offsetY = 0;
        let viewMode = 'template'; // 'template' or 'event'

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            canvas = document.getElementById('seatmap-canvas');
            ctx = canvas.getContext('2d');
            
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);
            
            canvas.addEventListener('click', handleCanvasClick);
            canvas.addEventListener('wheel', handleZoom);
            
            loadVenues();
            loadEvents();
            
            log('info', 'Seatmap Test Interface initialized');
        });

        function resizeCanvas() {
            const container = canvas.parentElement;
            canvas.width = container.clientWidth;
            canvas.height = 600;
            renderSeatMap();
        }

        // API Functions
        async function apiCall(method, endpoint, data = null) {
            const options = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            };
            
            if (data) {
                options.body = JSON.stringify(data);
            }
            
            const response = await fetch(`${API_BASE}${endpoint}`, options);
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.message || 'API Error');
            }
            
            return result;
        }

        async function loadVenues() {
            try {
                const result = await apiCall('GET', '/venues');
                const select = document.getElementById('venue-select');
                select.innerHTML = '<option value="">Select a venue...</option>';
                
                result.data.forEach(venue => {
                    const option = document.createElement('option');
                    option.value = venue.id;
                    option.textContent = `${venue.name} (${venue.venue_type})`;
                    select.appendChild(option);
                });
                
                select.addEventListener('change', handleVenueChange);
                log('success', `Loaded ${result.data.length} venues`);
            } catch (e) {
                log('error', `Failed to load venues: ${e.message}`);
            }
        }

        async function handleVenueChange(e) {
            const venueId = e.target.value;
            if (!venueId) {
                currentVenue = null;
                currentTemplate = null;
                return;
            }
            
            currentVenue = { id: venueId };
            await loadTemplates();
        }

        async function loadTemplates() {
            if (!currentVenue) return;
            
            try {
                const result = await apiCall('GET', `/venues/${currentVenue.id}/templates`);
                const select = document.getElementById('template-select');
                select.innerHTML = '<option value="">Select a template...</option>';
                
                result.data.forEach(template => {
                    const option = document.createElement('option');
                    option.value = template.id;
                    option.textContent = `${template.name} (${template.elements_count || 0} elements)`;
                    select.appendChild(option);
                });
                
                select.addEventListener('change', handleTemplateChange);
                document.getElementById('dup-template-btn').disabled = true;
                log('success', `Loaded ${result.data.length} templates`);
            } catch (e) {
                log('error', `Failed to load templates: ${e.message}`);
            }
        }

        async function handleTemplateChange(e) {
            const templateId = e.target.value;
            if (!templateId) {
                currentTemplate = null;
                templateElements = null;
                renderSeatMap();
                return;
            }
            
            currentTemplate = { id: templateId };
            document.getElementById('dup-template-btn').disabled = false;
            viewMode = 'template';
            
            await loadTemplateElements();
            await loadZones();
        }

        async function loadTemplateElements() {
            if (!currentTemplate) return;
            
            try {
                const result = await apiCall('GET', `/templates/${currentTemplate.id}`);
                currentTemplate.data = result.data.template;
                templateElements = result.data.template.elements || [];
                
                // Update zone select in generate form
                const zoneSelect = document.getElementById('gen-zone');
                zoneSelect.innerHTML = '<option value="">No zone</option>';
                zones.forEach(z => {
                    const opt = document.createElement('option');
                    opt.value = z.id;
                    opt.textContent = z.name;
                    zoneSelect.appendChild(opt);
                });
                
                renderTemplateCanvas();
                log('success', `Loaded ${templateElements.length} elements`);
            } catch (e) {
                log('error', `Failed to load elements: ${e.message}`);
            }
        }

        async function loadZones() {
            if (!currentTemplate) return;
            
            try {
                const result = await apiCall('GET', `/templates/${currentTemplate.id}/zones`);
                zones = result.data;
                
                const container = document.getElementById('zones-list');
                container.innerHTML = zones.map(z => `
                    <div style="display: flex; align-items: center; gap: 8px; padding: 8px; background: #0f172a; border-radius: 6px; margin-bottom: 8px;">
                        <div style="width: 16px; height: 16px; background: ${z.color}; border-radius: 4px;"></div>
                        <span style="flex: 1;">${z.name}</span>
                        <span style="color: #94a3b8; font-size: 0.75rem;">+$${z.base_price}</span>
                    </div>
                `).join('');
                
                log('success', `Loaded ${zones.length} zones`);
            } catch (e) {
                log('error', `Failed to load zones: ${e.message}`);
            }
        }

        async function loadEvents() {
            try {
                const result = await apiCall('GET', '/events');
                const select = document.getElementById('event-select');
                select.innerHTML = '<option value="">Select an event...</option>';
                
                result.data.forEach(event => {
                    const option = document.createElement('option');
                    option.value = event.id;
                    option.textContent = `${event.title} (${event.status})`;
                    select.appendChild(option);
                });
                
                document.getElementById('event-select').addEventListener('change', handleEventChange);
                log('success', `Loaded ${result.data.length} events`);
            } catch (e) {
                log('error', `Failed to load events: ${e.message}`);
            }
        }

        async function handleEventChange(e) {
            const eventId = e.target.value;
            if (!eventId) {
                currentEvent = null;
                seatMapData = null;
                return;
            }
            
            currentEvent = { id: eventId };
            viewMode = 'event';
            
            const selectedOption = e.target.options[e.target.selectedIndex];
            document.getElementById('publish-btn').disabled = !selectedOption.textContent.includes('draft');
            
            await loadSeatMap();
        }

        async function loadSeatMap() {
            if (!currentEvent) return;
            
            try {
                const result = await apiCall('GET', `/events/${currentEvent.id}/seatmap`);
                seatMapData = result.data;
                selectedElements.clear();
                updateSelectedSeats();
                renderSeatMap();
                updateStats();
                log('success', `Loaded seatmap with ${seatMapData.elements.length} elements`);
            } catch (e) {
                log('error', `Failed to load seatmap: ${e.message}`);
            }
        }

        async function createVenue() {
            const data = {
                name: document.getElementById('venue-name').value,
                venue_type: document.getElementById('venue-type').value,
                default_width: parseInt(document.getElementById('venue-width').value),
                default_height: parseInt(document.getElementById('venue-height').value),
            };
            
            try {
                const result = await apiCall('POST', '/venues', data);
                log('success', `Created venue: ${result.data.name}`);
                hideModal('venue-modal');
                loadVenues();
            } catch (e) {
                log('error', `Failed to create venue: ${e.message}`);
            }
        }

        async function createTemplate() {
            if (!currentVenue) {
                log('error', 'Please select a venue first');
                return;
            }
            
            const data = {
                name: document.getElementById('template-name').value,
                canvas_width: parseInt(document.getElementById('template-width').value),
                canvas_height: parseInt(document.getElementById('template-height').value),
                background_color: document.getElementById('template-bg-color').value,
                scale_factor: parseFloat(document.getElementById('template-scale').value),
            };
            
            try {
                const result = await apiCall('POST', `/venues/${currentVenue.id}/templates`, data);
                log('success', `Created template: ${result.data.name}`);
                hideModal('template-modal');
                loadTemplates();
            } catch (e) {
                log('error', `Failed to create template: ${e.message}`);
            }
        }

        async function duplicateTemplate() {
            if (!currentTemplate) return;
            
            try {
                const result = await apiCall('POST', `/templates/${currentTemplate.id}/duplicate`);
                log('success', `Duplicated template: ${result.data.name}`);
                loadTemplates();
            } catch (e) {
                log('error', `Failed to duplicate template: ${e.message}`);
            }
        }

        async function createZone() {
            if (!currentTemplate) {
                log('error', 'Please select a template first');
                return;
            }
            
            const data = {
                name: document.getElementById('zone-name').value,
                code: document.getElementById('zone-code').value,
                color: document.getElementById('zone-color').value,
                base_price: parseFloat(document.getElementById('zone-price').value) || 0,
            };
            
            try {
                const result = await apiCall('POST', `/templates/${currentTemplate.id}/zones`, data);
                log('success', `Created zone: ${result.data.name}`);
                hideModal('zone-modal');
                loadZones();
            } catch (e) {
                log('error', `Failed to create zone: ${e.message}`);
            }
        }

        async function createDefaultZones() {
            if (!currentTemplate) return;
            
            try {
                const result = await apiCall('POST', `/templates/${currentTemplate.id}/zones/create-defaults`);
                log('success', `Created ${result.data.length} default zones`);
                loadZones();
            } catch (e) {
                log('error', `Failed to create default zones: ${e.message}`);
            }
        }

        async function generateSeats() {
            if (!currentTemplate) {
                log('error', 'Please select a template first');
                return;
            }
            
            const data = {
                start_x: parseFloat(document.getElementById('gen-start-x').value),
                start_y: parseFloat(document.getElementById('gen-start-y').value),
                rows: parseInt(document.getElementById('gen-rows').value),
                seats_per_row: parseInt(document.getElementById('gen-seats-per-row').value),
                seat_width: parseFloat(document.getElementById('gen-seat-width').value),
                seat_height: parseFloat(document.getElementById('gen-seat-height').value),
                gap_x: parseFloat(document.getElementById('gen-gap-x').value),
                gap_y: parseFloat(document.getElementById('gen-gap-y').value),
                seat_type: document.getElementById('gen-seat-type').value,
            };
            
            const zoneId = document.getElementById('gen-zone').value;
            if (zoneId) data.zone_id = parseInt(zoneId);
            
            try {
                const result = await apiCall('POST', `/templates/${currentTemplate.id}/elements/generate-seats`, data);
                log('success', result.message);
                loadTemplateElements();
            } catch (e) {
                log('error', `Failed to generate seats: ${e.message}`);
            }
        }

        async function addElement() {
            if (!currentTemplate) {
                log('error', 'Please select a template first');
                return;
            }
            
            const data = {
                element_type: document.getElementById('element-type').value,
                x: parseFloat(document.getElementById('el-x').value),
                y: parseFloat(document.getElementById('el-y').value),
                width: parseFloat(document.getElementById('el-width').value),
                height: parseFloat(document.getElementById('el-height').value),
                data_json: {
                    label: document.getElementById('el-label').value || 'Element',
                },
            };
            
            try {
                const result = await apiCall('POST', `/templates/${currentTemplate.id}/elements`, data);
                log('success', `Created element: ${result.data.element_type}`);
                loadTemplateElements();
            } catch (e) {
                log('error', `Failed to create element: ${e.message}`);
            }
        }

        async function deleteAllElements() {
            if (!currentTemplate) return;
            if (!confirm('Delete all elements in this template?')) return;
            
            try {
                const result = await apiCall('GET', `/templates/${currentTemplate.id}/elements`);
                const ids = result.data.elements.map(e => e.id);
                
                if (ids.length === 0) {
                    log('info', 'No elements to delete');
                    return;
                }
                
                await apiCall('POST', '/elements/bulk-delete', { element_ids: ids });
                log('success', `Deleted ${ids.length} elements`);
                loadTemplateElements();
            } catch (e) {
                log('error', `Failed to delete elements: ${e.message}`);
            }
        }

        async function createEvent() {
            const templateId = document.getElementById('template-select').value;
            if (!templateId) {
                log('error', 'Please select a template first');
                return;
            }
            
            const data = {
                template_id: parseInt(templateId),
                title: document.getElementById('event-title').value,
                start_at: document.getElementById('event-start').value,
                end_at: document.getElementById('event-end').value,
                base_price: parseFloat(document.getElementById('event-price').value),
            };
            
            try {
                const result = await apiCall('POST', '/events', data);
                log('success', `Created event: ${result.data.title}`);
                hideModal('event-modal');
                loadEvents();
            } catch (e) {
                log('error', `Failed to create event: ${e.message}`);
            }
        }

        async function publishEvent() {
            if (!currentEvent) return;
            
            try {
                await apiCall('POST', `/events/${currentEvent.id}/publish`);
                log('success', 'Event published successfully');
                document.getElementById('publish-btn').disabled = true;
                loadEvents();
                loadSeatMap();
            } catch (e) {
                log('error', `Failed to publish event: ${e.message}`);
            }
        }

        async function lockSeats() {
            if (selectedElements.size === 0 || !currentEvent) return;
            
            try {
                const result = await apiCall('POST', '/bookings/lock', {
                    event_id: currentEvent.id,
                    element_ids: Array.from(selectedElements),
                });
                
                if (result.success) {
                    log('success', `Locked ${result.locked_elements.length} seats until ${result.expires_at}`);
                } else {
                    log('error', result.message);
                }
            } catch (e) {
                log('error', `Failed to lock seats: ${e.message}`);
            }
        }

        async function createBooking() {
            if (selectedElements.size === 0 || !currentEvent) return;
            
            const customerName = document.getElementById('customer-name').value;
            const customerEmail = document.getElementById('customer-email').value;
            
            if (!customerName || !customerEmail) {
                log('error', 'Please enter customer details');
                return;
            }
            
            try {
                const result = await apiCall('POST', '/bookings', {
                    event_id: currentEvent.id,
                    element_ids: Array.from(selectedElements),
                    customer_name: customerName,
                    customer_email: customerEmail,
                });
                
                if (result.success) {
                    log('success', `Booking created: ${result.booking.booking_reference}`);
                    log('info', `Total: $${result.pricing.total}`);
                    
                    // Auto-confirm for testing
                    await apiCall('POST', `/bookings/${result.booking.booking_reference}/confirm`);
                    log('success', 'Booking confirmed');
                    
                    selectedElements.clear();
                    updateSelectedSeats();
                    loadSeatMap();
                } else {
                    log('error', result.message);
                }
            } catch (e) {
                log('error', `Failed to create booking: ${e.message}`);
            }
        }

        async function resetDatabase() {
            if (!confirm('This will delete all data. Continue?')) return;
            
            log('info', 'Resetting database...');
            // In a real app, this would call a reset endpoint
            log('success', 'Database reset complete (demo)');
        }

        // Canvas Functions
        function renderSeatMap() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            if (viewMode === 'template') {
                renderTemplateCanvas();
            } else {
                renderEventCanvas();
            }
        }

        function renderTemplateCanvas() {
            if (!currentTemplate || !currentTemplate.data) {
                ctx.fillStyle = '#64748b';
                ctx.font = '16px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('Select a template to view elements', canvas.width / 2, canvas.height / 2);
                return;
            }
            
            const template = currentTemplate.data;
            const elements = templateElements || [];
            
            // Calculate scale to fit canvas
            const scaleX = (canvas.width - 40) / template.canvas_width;
            const scaleY = (canvas.height - 40) / template.canvas_height;
            scale = Math.min(scaleX, scaleY, 2);
            offsetX = (canvas.width - template.canvas_width * scale) / 2;
            offsetY = (canvas.height - template.canvas_height * scale) / 2;
            
            // Draw background
            ctx.fillStyle = template.background_color || '#1e293b';
            ctx.fillRect(offsetX, offsetY, template.canvas_width * scale, template.canvas_height * scale);
            
            // Draw grid
            if (template.show_grid) {
                ctx.strokeStyle = 'rgba(255,255,255,0.1)';
                ctx.lineWidth = 1;
                const gridSize = (template.grid_size || 10) * scale;
                
                for (let x = offsetX; x < offsetX + template.canvas_width * scale; x += gridSize) {
                    ctx.beginPath();
                    ctx.moveTo(x, offsetY);
                    ctx.lineTo(x, offsetY + template.canvas_height * scale);
                    ctx.stroke();
                }
                
                for (let y = offsetY; y < offsetY + template.canvas_height * scale; y += gridSize) {
                    ctx.beginPath();
                    ctx.moveTo(offsetX, y);
                    ctx.lineTo(offsetX + template.canvas_width * scale, y);
                    ctx.stroke();
                }
            }
            
            // Draw elements
            elements.forEach(el => {
                drawTemplateElement(el);
            });
        }

        function renderEventCanvas() {
            if (!seatMapData) {
                ctx.fillStyle = '#64748b';
                ctx.font = '16px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('Select an event to view seatmap', canvas.width / 2, canvas.height / 2);
                return;
            }
            
            const { event, elements } = seatMapData;
            
            // Calculate scale to fit canvas
            const scaleX = (canvas.width - 40) / event.canvas.width;
            const scaleY = (canvas.height - 40) / event.canvas.height;
            scale = Math.min(scaleX, scaleY, 2);
            offsetX = (canvas.width - event.canvas.width * scale) / 2;
            offsetY = (canvas.height - event.canvas.height * scale) / 2;
            
            // Draw background
            ctx.fillStyle = event.canvas.background_color || '#1e293b';
            ctx.fillRect(offsetX, offsetY, event.canvas.width * scale, event.canvas.height * scale);
            
            // Draw elements
            elements.forEach(el => {
                drawEventElement(el);
            });
        }

        function drawTemplateElement(el) {
            const x = offsetX + el.x * scale;
            const y = offsetY + el.y * scale;
            const w = el.width * scale;
            const h = el.height * scale;
            
            // Get color from style or default
            let color = el.style_json?.fill || '#64748b';
            
            // Draw element
            ctx.fillStyle = color;
            ctx.fillRect(x, y, w, h);
            
            // Draw border
            ctx.strokeStyle = el.style_json?.stroke || 'rgba(255,255,255,0.3)';
            ctx.lineWidth = (el.style_json?.strokeWidth || 1) * scale;
            ctx.strokeRect(x, y, w, h);
            
            // Draw label
            if (el.data_json?.label && scale > 0.3) {
                ctx.fillStyle = 'white';
                ctx.font = `${Math.max(8, 10 * scale)}px sans-serif`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(el.data_json.label, x + w/2, y + h/2);
            }
        }

        function drawEventElement(el) {
            const x = offsetX + el.x * scale;
            const y = offsetY + el.y * scale;
            const w = el.width * scale;
            const h = el.height * scale;
            
            // Determine color based on status
            let color;
            if (selectedElements.has(el.id)) {
                color = '#3b82f6'; // Blue - selected
            } else if (el.status === 'booked') {
                color = '#ef4444'; // Red - booked
            } else if (el.status === 'locked') {
                color = '#f59e0b'; // Orange - locked
            } else if (el.is_bookable) {
                color = '#10b981'; // Green - available
            } else {
                color = '#64748b'; // Gray - not bookable
            }
            
            // Draw element
            ctx.fillStyle = color;
            ctx.fillRect(x, y, w, h);
            
            // Draw border
            ctx.strokeStyle = 'rgba(255,255,255,0.3)';
            ctx.lineWidth = 1;
            ctx.strokeRect(x, y, w, h);
            
            // Draw label if seat
            if (el.data && el.data.label && scale > 0.5) {
                ctx.fillStyle = 'white';
                ctx.font = `${Math.max(10, 12 * scale)}px sans-serif`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(el.data.label, x + w/2, y + h/2);
            }
        }

        function handleCanvasClick(e) {
            const rect = canvas.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;
            
            if (viewMode === 'event') {
                handleEventCanvasClick(mouseX, mouseY);
            }
        }

        function handleEventCanvasClick(mouseX, mouseY) {
            if (!seatMapData) return;
            
            // Find clicked element
            for (const el of seatMapData.elements) {
                const x = offsetX + el.x * scale;
                const y = offsetY + el.y * scale;
                const w = el.width * scale;
                const h = el.height * scale;
                
                if (mouseX >= x && mouseX <= x + w && mouseY >= y && mouseY <= y + h) {
                    if (!el.is_bookable) {
                        log('info', `Element ${el.id} is not bookable`);
                        return;
                    }
                    
                    if (el.status === 'booked') {
                        log('info', `Element ${el.id} is already booked`);
                        return;
                    }
                    
                    if (el.status === 'locked') {
                        log('info', `Element ${el.id} is currently locked`);
                        return;
                    }
                    
                    if (selectedElements.has(el.id)) {
                        selectedElements.delete(el.id);
                    } else {
                        selectedElements.add(el.id);
                    }
                    
                    updateSelectedSeats();
                    renderSeatMap();
                    return;
                }
            }
        }

        function handleZoom(e) {
            e.preventDefault();
            // Zoom implementation would go here
        }

        // UI Functions
        function updateSelectedSeats() {
            const container = document.getElementById('selected-seats');
            container.innerHTML = '';
            
            selectedElements.forEach(id => {
                const el = seatMapData?.elements.find(e => e.id === id);
                if (el) {
                    const div = document.createElement('div');
                    div.className = 'selected-seat';
                    div.innerHTML = `
                        ${el.data?.label || `Seat ${id}`}
                        <button onclick="removeSeat(${id})">×</button>
                    `;
                    container.appendChild(div);
                }
            });
            
            document.getElementById('book-btn').disabled = selectedElements.size === 0;
            document.getElementById('lock-btn').disabled = selectedElements.size === 0;
            
            updatePricing();
        }

        function removeSeat(id) {
            selectedElements.delete(id);
            updateSelectedSeats();
            renderSeatMap();
        }

        function updatePricing() {
            const count = selectedElements.size;
            const basePrice = currentEvent?.base_price || 50;
            const subtotal = count * basePrice;
            const serviceFee = subtotal * 0.05;
            const tax = subtotal * 0.10;
            const total = subtotal + serviceFee + tax;
            
            document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
            document.getElementById('service-fee').textContent = `$${serviceFee.toFixed(2)}`;
            document.getElementById('tax').textContent = `$${tax.toFixed(2)}`;
            document.getElementById('total').textContent = `$${total.toFixed(2)}`;
        }

        function updateStats() {
            if (!seatMapData) {
                document.getElementById('total-seats').textContent = '0';
                document.getElementById('available-seats').textContent = '0';
                document.getElementById('locked-seats').textContent = '0';
                document.getElementById('booked-seats').textContent = '0';
                return;
            }
            
            const elements = seatMapData.elements.filter(e => e.is_bookable);
            
            document.getElementById('total-seats').textContent = elements.length;
            document.getElementById('available-seats').textContent = elements.filter(e => e.status === 'available').length;
            document.getElementById('locked-seats').textContent = elements.filter(e => e.status === 'locked').length;
            document.getElementById('booked-seats').textContent = elements.filter(e => e.status === 'booked').length;
        }

        function clearSelection() {
            selectedElements.clear();
            updateSelectedSeats();
            renderSeatMap();
            log('info', 'Selection cleared');
        }

        function log(type, message) {
            const container = document.getElementById('log-container');
            const time = new Date().toLocaleTimeString();
            
            const entry = document.createElement('div');
            entry.className = `log-entry log-${type}`;
            entry.innerHTML = `<span class="log-time">[${time}]</span> ${message}`;
            
            container.insertBefore(entry, container.firstChild);
            
            // Keep only last 50 entries
            while (container.children.length > 50) {
                container.removeChild(container.lastChild);
            }
        }

        function showCreateVenueModal() {
            document.getElementById('venue-modal').style.display = 'flex';
        }

        function showCreateTemplateModal() {
            if (!currentVenue) {
                log('error', 'Please select a venue first');
                return;
            }
            document.getElementById('template-modal').style.display = 'flex';
        }

        function showCreateZoneModal() {
            if (!currentTemplate) {
                log('error', 'Please select a template first');
                return;
            }
            document.getElementById('zone-modal').style.display = 'flex';
        }

        function showCreateEventModal() {
            const templateId = document.getElementById('template-select').value;
            if (!templateId) {
                log('error', 'Please select a template first');
                return;
            }
            document.getElementById('event-modal').style.display = 'flex';
        }

        function hideModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function switchTab(tab) {
            // Hide all tabs
            document.getElementById('tab-venues').classList.add('hidden');
            document.getElementById('tab-templates').classList.add('hidden');
            document.getElementById('tab-booking').classList.add('hidden');
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(`tab-${tab}`).classList.remove('hidden');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
