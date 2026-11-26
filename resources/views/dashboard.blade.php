<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>نظام مراقبة المحولات الكهربائية</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }
        .pulse-marker {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #ef4444;
            border: 3px solid #ffffff;
            box-shadow: 0 0 0 rgba(239, 68, 68, 0.7);
            animation: pulse 1.4s infinite;
        }
        .normal-marker {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #10b981;
            border: 3px solid #ffffff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.4);
        }
        .offline-marker {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #64748b;
            border: 2px solid #ffffff;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.8); }
            70% { box-shadow: 0 0 0 18px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">

    <!-- الشريط العلوي -->
    <header class="bg-slate-900 border-b border-slate-800 px-6 py-4 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
            <h1 class="text-xl font-bold text-white">مركز مراقبة المحولات الكهربائية</h1>
        </div>
        <div class="flex items-center gap-4 text-sm text-slate-400">
            <span id="sync-status">جاري المزامنة...</span>
            <button onclick="fetchDashboardData()" class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-1.5 rounded-lg transition">
                تحديث الآن
            </button>
        </div>
    </header>

    <main class="p-6 max-w-[1600px] mx-auto space-y-6">

        <!-- بطاقات المؤشرات السريعة -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
                <p class="text-slate-400 text-sm">المحولات المسجلة</p>
                <p id="stat-total" class="text-3xl font-bold mt-1">0</p>
            </div>
            <div id="alarm-card" class="bg-slate-900 border border-slate-800 rounded-xl p-4 transition">
                <p class="text-slate-400 text-sm">الأعطال النشطة الآن</p>
                <p id="stat-alarms" class="text-3xl font-bold mt-1 text-emerald-400">0</p>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
                <p class="text-slate-400 text-sm">عقد جديدة بانتظار التعيين</p>
                <p id="stat-unassigned" class="text-3xl font-bold mt-1 text-amber-400">0</p>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
                <p class="text-slate-400 text-sm">متصل حالياً</p>
                <p id="stat-online" class="text-3xl font-bold mt-1 text-sky-400">0</p>
            </div>
        </section>

        <!-- الخريطة + لوحة التحكم الجانبية -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- الخريطة التفاعلية -->
            <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden flex flex-col">
                <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between">
                    <h2 class="font-semibold">خريطة الأحياء والمحولات</h2><button onclick="centerOnUserLocation()" class="bg-sky-600 hover:bg-sky-500 text-white text-xs px-3 py-1 rounded-lg transition">
    📍 موقعي
</button>
                    <div class="flex items-center gap-4 text-xs text-slate-400">
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> سليم</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> إنذار / فصل</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-500"></span> منقطع</span>
                    </div>
                </div>
                <div id="main-map" class="w-full h-[520px] z-0"></div>
            </div>

            <!-- اللوحة الجانبية (الأعطال + الأجهزة الجديدة + القائمة) -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl flex flex-col h-[568px]">
                <div class="flex border-b border-slate-800 text-sm">
                    <button onclick="switchTab('alarms')" id="tab-btn-alarms"
                        class="flex-1 py-3 font-medium border-b-2 border-red-500 text-white">
                        الأعطال (<span id="badge-alarms">0</span>)
                    </button>
                    <button onclick="switchTab('unassigned')" id="tab-btn-unassigned"
                        class="flex-1 py-3 font-medium border-b-2 border-transparent text-slate-400 hover:text-white">
                        جديد (<span id="badge-unassigned">0</span>)
                    </button>
                    <button onclick="switchTab('all')" id="tab-btn-all"
                        class="flex-1 py-3 font-medium border-b-2 border-transparent text-slate-400 hover:text-white">
                        الكل
                    </button>
                </div>

                <!-- تبويب الأعطال النشطة -->
                <div id="tab-alarms" class="p-4 space-y-3 overflow-y-auto flex-1"></div>

                <!-- تبويب الأجهزة المكتشفة حديثاً -->
                <div id="tab-unassigned" class="p-4 space-y-3 overflow-y-auto flex-1 hidden"></div>

                <!-- تبويب كل المحولات -->
                <div id="tab-all" class="p-4 space-y-3 overflow-y-auto flex-1 hidden"></div>
            </div>
        </section>

        <!-- سجل الأعطال التاريخي -->
        <section class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-800">
                <h2 class="font-semibold">سجل الإنذارات الأخيرة (Event Log)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="bg-slate-800/60 text-slate-400">
                        <tr>
                            <th class="px-5 py-3">المحولة</th>
                            <th class="px-5 py-3">الحي</th>
                            <th class="px-5 py-3">الحرارة</th>
                            <th class="px-5 py-3">الرطوبة</th>
                            <th class="px-5 py-3">الوقت</th>
                        </tr>
                    </thead>
                    <tbody id="alarm-logs-body" class="divide-y divide-slate-800"></tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- نافذة تعيين اسم وموقع المحولة (Zero-Touch Provisioning) -->
    <div id="assign-modal" class="fixed inset-0 bg-black/75 hidden items-center justify-center z-50 p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-xl p-6 space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold">إعداد وتعيين موقع المحولة</h3>
                <button onclick="closeAssignModal()" class="text-slate-400 hover:text-white text-xl">&times;</button>
            </div>

            <form id="assign-form" class="space-y-4">
                <input type="hidden" id="assign-id">
                <div class="text-xs bg-slate-800 px-3 py-2 rounded-lg text-slate-300 font-mono" dir="ltr" id="assign-mac"></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-slate-300 mb-1">اسم المحولة</label>
                        <input type="text" id="assign-name" required placeholder="مثال: محولة الجامع"
                            class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-sky-500">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-300 mb-1">الحي / المنطقة</label>
                        <input type="text" id="assign-neighborhood" required placeholder="مثال: الجميلية"
                            class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-sky-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm text-slate-300 mb-1">انقر على الخريطة لتحديد موقع المحولة</label>
                    <div id="picker-map" class="w-full h-56 rounded-lg border border-slate-700"></div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">خط العرض (Latitude)</label>
                        <input type="number" step="any" id="assign-lat" required
                            class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-sm font-mono text-white">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">خط الطول (Longitude)</label>
                        <input type="number" step="any" id="assign-lng" required
                            class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-sm font-mono text-white">
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeAssignModal()" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-sm">إلغاء</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-sm">حفظ وتفعيل</button>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة الرسم البياني التاريخي -->
    <div id="chart-modal" class="fixed inset-0 bg-black/75 hidden items-center justify-center z-50 p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-2xl p-6 space-y-4">
            <div class="flex justify-between items-center">
                <h3 id="chart-title" class="text-lg font-bold">سجل القراءات</h3>
                <button onclick="closeChartModal()" class="text-slate-400 hover:text-white text-xl">&times;</button>
            </div>
            <div class="h-72">
                <canvas id="history-chart"></canvas>
            </div>
        </div>
    </div>

    <script>
      let currentLat = 36.2021; // موقع احتياطي في حال رفض إذن الموقع
        let currentLng = 37.1343;
        let userLocationMarker = null;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        // تهيئة الخريطة الرئيسية
        const mainMap = L.map('main-map').setView([currentLat, currentLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(mainMap);

        // دالة جلب الموقع الحالي وتوجيه الخريطة إليه
        function centerOnUserLocation() {
            if (!navigator.geolocation) return;

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    currentLat = position.coords.latitude;
                    currentLng = position.coords.longitude;

                    // تحريك الخريطة فوراً لموقعك
                    mainMap.setView([currentLat, currentLng], 15);

                    // وضع نقطة زرقاء توضّح موقعك الحالي
                    if (userLocationMarker) {
                        userLocationMarker.setLatLng([currentLat, currentLng]);
                    } else {
                        userLocationMarker = L.circleMarker([currentLat, currentLng], {
                            radius: 7,
                            fillColor: '#38bdf8',
                            color: '#ffffff',
                            weight: 2,
                            fillOpacity: 1
                        }).addTo(mainMap).bindPopup('موقعي الحالي');
                    }
                },
                (err) => {
                    console.warn('تعذر تحديد الموقع تلقائياً:', err.message);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }

        // استدعاء الدالة فور فتح الصفحة
        centerOnUserLocation();
        const markers = {};
        let pickerMap = null;
        let pickerMarker = null;
        let historyChart = null;
        let latestData = null;

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>"']/g, m => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[m]));
        }

        function createMarkerIcon(t) {
            let cls = 'normal-marker';
            if (t.alarm_active) cls = 'pulse-marker';
            else if (!t.is_online) cls = 'offline-marker';
            return L.divIcon({ className: '', html: `<div class="${cls}"></div>`, iconSize: [18, 18], iconAnchor: [9, 9] });
        }

        function updateMap(assigned) {
            const activeIds = new Set();

            assigned.forEach(t => {
                if (t.latitude === null || t.longitude === null) return;
                activeIds.add(t.id);

                const popupHtml = `
                    <div dir="rtl" style="min-width:170px; font-family:system-ui">
                        <div style="font-weight:bold; font-size:14px; margin-bottom:4px">${escapeHtml(t.name)}</div>
                        <div style="color:#64748b; font-size:12px; margin-bottom:6px">الحي: ${escapeHtml(t.neighborhood)}</div>
                        <div>الحرارة: <b>${t.last_temp ?? '-'} °C</b></div>
                        <div>الرطوبة: <b>${t.last_hum ?? '-'} %</b></div>
                        <div style="margin-top:4px; color:${t.alarm_active ? '#dc2626' : '#059669'}; font-weight:bold">
                            ${t.alarm_active ? '⚠️ إنذار - الريليه مفصول' : '✅ الوضع طبيعي'}
                        </div>
                        <div style="margin-top:8px; display:flex; gap:6px">
                            <button onclick="openHistory(${t.id})" style="background:#0284c7; color:#fff; border:none; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:12px">الرسم البياني</button>
                            <button onclick="openAssignModal(${t.id})" style="background:#475569; color:#fff; border:none; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:12px">تعديل</button>
                        </div>
                    </div>
                `;

                if (markers[t.id]) {
                    markers[t.id].setLatLng([t.latitude, t.longitude]);
                    markers[t.id].setIcon(createMarkerIcon(t));
                    markers[t.id].setPopupContent(popupHtml);
                } else {
                    markers[t.id] = L.marker([t.latitude, t.longitude], { icon: createMarkerIcon(t) })
                        .addTo(mainMap)
                        .bindPopup(popupHtml);
                }
            });

            Object.keys(markers).forEach(id => {
                if (!activeIds.has(Number(id))) {
                    mainMap.removeLayer(markers[id]);
                    delete markers[id];
                }
            });
        }

        function renderPanels(data) {
            // الإحصائيات
            document.getElementById('stat-total').textContent = data.stats.total;
            document.getElementById('stat-alarms').textContent = data.stats.alarms;
            document.getElementById('stat-unassigned').textContent = data.stats.unassigned;
            document.getElementById('stat-online').textContent = data.stats.online;
            document.getElementById('badge-alarms').textContent = data.stats.alarms;
            document.getElementById('badge-unassigned').textContent = data.stats.unassigned;

            const alarmCard = document.getElementById('alarm-card');
            const alarmNum = document.getElementById('stat-alarms');
            if (data.stats.alarms > 0) {
                alarmCard.classList.add('border-red-600', 'bg-red-950/30');
                alarmNum.className = 'text-3xl font-bold mt-1 text-red-500';
            } else {
                alarmCard.classList.remove('border-red-600', 'bg-red-950/30');
                alarmNum.className = 'text-3xl font-bold mt-1 text-emerald-400';
            }

            // 1. تبويب الأعطال النشطة
            const alarmsEl = document.getElementById('tab-alarms');
            if (data.active_alarms.length === 0) {
                alarmsEl.innerHTML = `<div class="text-center text-slate-500 py-12">لا توجد أعطال نشطة حالياً — الشبكة مستقرة.</div>`;
            } else {
                alarmsEl.innerHTML = data.active_alarms.map(t => `
                    <div class="bg-red-950/40 border border-red-700/70 rounded-xl p-3.5 space-y-2">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-bold text-red-300">${escapeHtml(t.name ?? t.mac_address)}</p>
                                <p class="text-xs text-slate-400">${escapeHtml(t.neighborhood ?? 'غير معيّن على الخريطة')}</p>
                            </div>
                            <span class="bg-red-600 text-white text-xs px-2 py-0.5 rounded-full font-bold">${t.last_temp}°C</span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-400 pt-1">
                            <span>الرطوبة: ${t.last_hum}%</span>
                            <div class="flex gap-2">
                                ${t.latitude ? `<button onclick="focusOnMap(${t.id}, ${t.latitude}, ${t.longitude})" class="text-sky-400 hover:underline">عرض على الخريطة</button>` : ''}
                                <button onclick="openHistory(${t.id})" class="text-amber-400 hover:underline">الرسم البياني</button>
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            // 2. تبويب الأجهزة الجديدة غير المسماة
            const unassignedEl = document.getElementById('tab-unassigned');
            if (data.unassigned.length === 0) {
                unassignedEl.innerHTML = `<div class="text-center text-slate-500 py-12">لا توجد عقد جديدة مكتشفة.</div>`;
            } else {
                unassignedEl.innerHTML = data.unassigned.map(t => `
                    <div class="bg-slate-800/70 border border-amber-500/40 rounded-xl p-3.5 space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="font-mono text-xs text-amber-300" dir="ltr">${escapeHtml(t.mac_address)}</span>
                            <span class="text-xs text-slate-400">${t.last_temp ?? '-'}°C</span>
                        </div>
                        <div class="flex justify-between items-center text-xs text-slate-400">
                            <span>آخر إشارة: ${escapeHtml(t.last_seen)}</span>
                            <button onclick="openAssignModal(${t.id})"
                                class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold px-3 py-1 rounded-lg">
                                تعيين الاسم والموقع
                            </button>
                        </div>
                    </div>
                `).join('');
            }

            // 3. تبويب الكل
            const allEl = document.getElementById('tab-all');
            if (data.assigned.length === 0) {
                allEl.innerHTML = `<div class="text-center text-slate-500 py-12">لم يتم تعيين أي محولات بعد.</div>`;
            } else {
                allEl.innerHTML = data.assigned.map(t => `
                    <div class="bg-slate-800/50 border border-slate-700/70 rounded-xl p-3 space-y-1.5">
                        <div class="flex justify-between items-center">
                            <button onclick="focusOnMap(${t.id}, ${t.latitude}, ${t.longitude})" class="font-semibold text-right hover:text-sky-400">
                                ${escapeHtml(t.name)}
                            </button>
                            <span class="text-xs px-2 py-0.5 rounded-full ${t.alarm_active ? 'bg-red-600 text-white' : (t.is_online ? 'bg-emerald-600/30 text-emerald-300' : 'bg-slate-700 text-slate-400')}">
                                ${t.alarm_active ? 'إنذار' : (t.is_online ? 'متصل' : 'منقطع')}
                            </span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-400">
                            <span>${escapeHtml(t.neighborhood)} | ${t.last_temp ?? '-'}°C</span>
                            <div class="flex gap-2">
                                <button onclick="openHistory(${t.id})" class="text-sky-400 hover:underline">مخطط</button>
                                <button onclick="openAssignModal(${t.id})" class="text-slate-300 hover:underline">تعديل</button>
                                <button onclick="deleteTransformer(${t.id})" class="text-red-400 hover:underline">حذف</button>
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            // 4. سجل الأعطال
            const logsBody = document.getElementById('alarm-logs-body');
            if (data.alarm_logs.length === 0) {
                logsBody.innerHTML = `<tr><td colspan="5" class="text-center py-6 text-slate-500">لا توجد إنذارات مسجلة.</td></tr>`;
            } else {
                logsBody.innerHTML = data.alarm_logs.map(log => `
                    <tr class="hover:bg-slate-800/40">
                        <td class="px-5 py-3 font-medium text-red-300">${escapeHtml(log.transformer_name)}</td>
                        <td class="px-5 py-3 text-slate-300">${escapeHtml(log.neighborhood)}</td>
                        <td class="px-5 py-3 font-bold text-red-400">${log.temp}°C</td>
                        <td class="px-5 py-3 text-slate-300">${log.hum}%</td>
                        <td class="px-5 py-3 text-slate-400 font-mono text-xs" dir="ltr">${escapeHtml(log.time)}</td>
                    </tr>
                `).join('');
            }
        }

        function switchTab(name) {
            ['alarms', 'unassigned', 'all'].forEach(t => {
                document.getElementById(`tab-${t}`).classList.toggle('hidden', t !== name);
                const btn = document.getElementById(`tab-btn-${t}`);
                btn.classList.toggle('border-red-500', t === name && name === 'alarms');
                btn.classList.toggle('border-amber-400', t === name && name === 'unassigned');
                btn.classList.toggle('border-sky-400', t === name && name === 'all');
                btn.classList.toggle('border-transparent', t !== name);
                btn.classList.toggle('text-white', t === name);
                btn.classList.toggle('text-slate-400', t !== name);
            });
        }

        function focusOnMap(id, lat, lng) {
            mainMap.flyTo([lat, lng], 16);
            if (markers[id]) markers[id].openPopup();
        }

        function openAssignModal(id) {
            const all = [...(latestData?.assigned ?? []), ...(latestData?.unassigned ?? [])];
            const t = all.find(item => item.id === id);
            if (!t) return;

            document.getElementById('assign-id').value = t.id;
            document.getElementById('assign-mac').textContent = `MAC: ${t.mac_address}`;
            document.getElementById('assign-name').value = t.name ?? '';
            document.getElementById('assign-neighborhood').value = t.neighborhood ?? '';

            const lat = t.latitude ?? currentLat;
            const lng = t.longitude ?? currentLng;
            document.getElementById('assign-lat').value = lat;
            document.getElementById('assign-lng').value = lng;

            const modal = document.getElementById('assign-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            setTimeout(() => {
                if (!pickerMap) {
                    pickerMap = L.map('picker-map').setView([lat, lng], 14);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(pickerMap);
                    pickerMarker = L.marker([lat, lng]).addTo(pickerMap);

                    pickerMap.on('click', e => {
                        const { lat: cLat, lng: cLng } = e.latlng;
                        pickerMarker.setLatLng([cLat, cLng]);
                        document.getElementById('assign-lat').value = cLat.toFixed(6);
                        document.getElementById('assign-lng').value = cLng.toFixed(6);
                    });
                } else {
                    pickerMap.invalidateSize();
                    pickerMap.setView([lat, lng], 14);
                    pickerMarker.setLatLng([lat, lng]);
                }
            }, 150);
        }

        function closeAssignModal() {
            const modal = document.getElementById('assign-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        document.getElementById('assign-form').addEventListener('submit', async e => {
            e.preventDefault();
            const id = document.getElementById('assign-id').value;
            const payload = {
                name: document.getElementById('assign-name').value,
                neighborhood: document.getElementById('assign-neighborhood').value,
                latitude: parseFloat(document.getElementById('assign-lat').value),
                longitude: parseFloat(document.getElementById('assign-lng').value),
            };

            const res = await fetch(`/transformers/${id}/assign`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (res.ok) {
                closeAssignModal();
                fetchDashboardData();
            }
        });

        async function openHistory(id) {
            const res = await fetch(`/transformers/${id}/history`);
            const data = await res.json();

            document.getElementById('chart-title').textContent = `سجل القراءات: ${data.name}`;
            const modal = document.getElementById('chart-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            const ctx = document.getElementById('history-chart').getContext('2d');
            if (historyChart) historyChart.destroy();

            historyChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.readings.map(r => r.time),
                    datasets: [
                        {
                            label: 'الحرارة (°C)',
                            data: data.readings.map(r => r.temp),
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.15)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'الرطوبة (%)',
                            data: data.readings.map(r => r.hum),
                            borderColor: '#38bdf8',
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' } }
                    }
                }
            });
        }

        function closeChartModal() {
            const modal = document.getElementById('chart-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        async function deleteTransformer(id) {
            if (!confirm('حذف هذه المحولة وسجلها بالكامل؟')) return;
            await fetch(`/transformers/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            });
            fetchDashboardData();
        }

        async function fetchDashboardData() {
            try {
                const res = await fetch('/dashboard/data');
                const data = await res.json();
                latestData = data;
                updateMap(data.assigned);
                renderPanels(data);
                document.getElementById('sync-status').textContent = `آخر تحديث: ${new Date().toLocaleTimeString('ar-SY')}`;
            } catch (err) {
                document.getElementById('sync-status').textContent = 'تعذر الاتصال بالسيرفر';
            }
        }

        fetchDashboardData();
        setInterval(fetchDashboardData, 4000);
    </script>
</body>
</html>