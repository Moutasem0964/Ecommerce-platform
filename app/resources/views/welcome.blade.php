<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سوق التقنية</title>
    <!-- استيراد خط تجوال العربي و Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Tajawal', system-ui, -apple-system, sans-serif; background-color: #f8fafc; color: #0f172a; margin: 0; }
        code, pre, .font-mono { font-family: 'Fira Code', monospace; }
        
        .page-tab { display: none; }
        .page-tab.active { display: block; }
        .app-modal { display: none; }
        .app-modal.active { display: flex; }
        
        .nav-btn.active { color: #2563eb; background-color: #eff6ff; font-weight: 700; }
        .category-chip.active { background-color: #2563eb; color: #ffffff; border-color: #2563eb; }
        
        button { cursor: pointer; font-family: inherit; }
        button:disabled { cursor: not-allowed; opacity: 0.5; }
        input, select, textarea { font-family: inherit; }
        
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        @media print {
            body * { visibility: hidden !important; }
            #modalOrderDetails, #modalOrderDetails * { visibility: visible !important; }
            #modalOrderDetails {
                position: absolute !important;
                inset: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                max-height: none !important;
                display: block !important;
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
            #modalOrderDetails button { display: none !important; }
            #modalOrderDetails .overflow-y-auto { overflow: visible !important; max-height: none !important; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased selection:bg-blue-600 selection:text-white">

    <!-- شريط التنقل الرئيسي (Header) -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="h-16 flex items-center justify-between gap-4">
                
                <!-- الشعار وهوية المتجر -->
                <div class="flex items-center gap-6">
                    <div class="cursor-pointer" onclick="switchTab('catalog')">
                        <span class="text-xl font-black text-slate-900 tracking-tight">سوق التقنية</span>
                    </div>

                    <!-- روابط التنقل الأساسية -->
                    <nav class="hidden md:flex items-center gap-1 text-xs font-semibold">
                        <button onclick="switchTab('catalog')" id="nav-catalog" class="nav-btn active px-3.5 py-2 rounded-lg transition flex items-center gap-1.5">
                            المنتجات
                        </button>
                        <button onclick="switchTab('orders')" id="nav-orders" class="nav-btn px-3.5 py-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition flex items-center gap-1.5">
                            طلباتي
                        </button>
                    </nav>
                </div>

                <!-- شريط البحث المباشر -->
                <div class="flex-1 max-w-sm hidden lg:block">
                    <div class="relative">
                        <input type="text" id="liveSearchInput" oninput="handleSearch(this.value)" placeholder="ابحث عن المنتجات، المواصفات، التصنيفات..." class="w-full bg-slate-100 border border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-100 rounded-lg text-xs py-2 pr-9 pl-3 transition outline-none">
                        <svg class="w-4 h-4 text-slate-400 absolute right-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>

                <!-- إجراءات المستخدم وسلة الشراء -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- سلة المشتريات -->
                    <button onclick="openCartDrawer()" class="relative p-2 text-slate-700 hover:text-blue-600 hover:bg-slate-100 rounded-lg transition flex items-center gap-2 border border-slate-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <span class="hidden sm:inline text-xs font-bold">السلة</span>
                        <span id="cartCountBadge" class="w-5 h-5 bg-blue-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center">0</span>
                    </button>

                    <!-- الإشعارات -->
                    <button onclick="switchTab('notifications')" class="relative p-2 text-slate-700 hover:text-blue-600 hover:bg-slate-100 rounded-lg transition border border-slate-200" title="الإشعارات">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <span id="notifBadge" class="hidden absolute -top-1 -right-1 w-4 h-4 bg-red-600 text-white text-[9px] font-bold rounded-full flex items-center justify-center">0</span>
                    </button>

                    <!-- قسم حساب المستخدم: تسجيل الدخول أو القائمة الشخصية -->
                    <div id="authContainer">
                        <!-- يتم التبديل تلقائياً بين زر تسجيل الدخول وقائمة المستخدم المسجل -->
                    </div>
                </div>
            </div>

            <!-- شريط روابط الهواتف المحمولة -->
            <div class="flex md:hidden gap-1 border-t border-slate-100 py-2 overflow-x-auto text-xs font-semibold">
                <button onclick="switchTab('catalog')" class="px-3 py-1.5 rounded text-blue-600 bg-blue-50">المنتجات</button>
                <button onclick="switchTab('orders')" class="px-3 py-1.5 rounded text-slate-600">طلباتي</button>
                <button onclick="switchTab('notifications')" class="px-3 py-1.5 rounded text-slate-600">الإشعارات</button>
            </div>
        </div>
    </header>

    <!-- شريط التنبيهات اللحظي (Toast Notification) -->
    <div id="toastContainer" class="fixed top-20 left-1/2 -translate-x-1/2 z-50 w-full max-w-md px-4 pointer-events-none transition-all duration-300 opacity-0 -translate-y-4">
        <div id="toastAlert" class="pointer-events-auto p-3.5 rounded-xl border shadow-lg flex items-center justify-between text-xs bg-white">
            <div class="flex items-center gap-2.5">
                <span id="toastIcon" class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-xs"></span>
                <span id="toastMessage" class="font-bold text-slate-800"></span>
            </div>
            <button onclick="hideToast()" class="text-slate-400 hover:text-slate-700 font-bold px-1.5">×</button>
        </div>
    </div>

    <!-- المحتوى الرئيسي للمتجر -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex-1 w-full">

        <!-- ======================================================== -->
        <!-- 1. صفحة المنتجات والكتالوج -->
        <!-- ======================================================== -->
        <section id="page-catalog" class="page-tab active space-y-6">
            
            <!-- شريط التصنيفات والتحكم بالمنتجات -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4">
                <!-- أزرار التصنيف -->
                <div class="flex items-center gap-1.5 flex-wrap text-xs">
                    <span class="text-slate-400 font-semibold ml-2">التصنيف:</span>
                    <button onclick="filterByCategory('', this)" class="category-chip active px-3.5 py-1.5 rounded-lg border border-slate-200 font-bold transition">الكل</button>
                    <button onclick="filterByCategory('إضاءة', this)" class="category-chip bg-slate-50 text-slate-700 hover:bg-slate-100 px-3.5 py-1.5 rounded-lg border border-slate-200 font-bold transition">إضاءة</button>
                    <button onclick="filterByCategory('إلكترونيات', this)" class="category-chip bg-slate-50 text-slate-700 hover:bg-slate-100 px-3.5 py-1.5 rounded-lg border border-slate-200 font-bold transition">إلكترونيات</button>
                    <button onclick="filterByCategory('أثاث', this)" class="category-chip bg-slate-50 text-slate-700 hover:bg-slate-100 px-3.5 py-1.5 rounded-lg border border-slate-200 font-bold transition">أثاث</button>
                    <button onclick="filterByCategory('منزل', this)" class="category-chip bg-slate-50 text-slate-700 hover:bg-slate-100 px-3.5 py-1.5 rounded-lg border border-slate-200 font-bold transition">منزل</button>
                </div>

                <!-- زر إضافة منتج جديد (يظهر فقط عند تسجيل الدخول بصلاحية مشرف) -->
                <div class="flex items-center gap-3">
                    <button onclick="openProductModal()" id="btnAdminAddProduct" class="hidden px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>إضافة منتج جديد</span>
                    </button>
                </div>
            </div>

            <!-- شبكة عرض المنتجات -->
            <div id="productsGridContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- يتم التحميل التلقائي عبر JavaScript -->
            </div>

            <!-- شريط التنقل بين الصفحات -->
            <div class="bg-white px-5 py-3.5 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between text-xs text-slate-600">
                <span id="pageStatsLabel">جاري قراءة المنتجات...</span>
                <div class="flex items-center gap-2">
                    <button onclick="navigateCatalogPage(-1)" id="btnPagePrev" class="px-3.5 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 font-bold transition disabled:opacity-40" disabled>السابق</button>
                    <button onclick="navigateCatalogPage(1)" id="btnPageNext" class="px-3.5 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 font-bold transition disabled:opacity-40" disabled>التالي</button>
                </div>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- 2. صفحة الطلبات والمشتريات -->
        <!-- ======================================================== -->
        <section id="page-orders" class="page-tab space-y-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <h2 id="ordersMainTitle" class="text-base font-extrabold text-slate-900">سجل الطلبات والمشتريات</h2>
                        <p id="ordersSubTitle" class="text-xs text-slate-500 mt-0.5">متابعة مسار معالجة الطلبات وحالات الشحن</p>
                    </div>
                    <button onclick="loadOrdersData()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition">تحديث القائمة</button>
                </div>

                <!-- جدول عرض الطلبات -->
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                                <th class="p-3.5">رقم الطلب</th>
                                <th class="p-3.5">تفاصيل العناصر</th>
                                <th class="p-3.5">المجموع الإجمالي</th>
                                <th class="p-3.5">الحالة الحالية</th>
                                <th class="p-3.5">تاريخ الإنشاء</th>
                                <th class="p-3.5">الإجراء المتاح</th>
                            </tr>
                        </thead>
                        <tbody id="ordersListRows" class="divide-y divide-slate-100">
                            <!-- صفوف الطلبات -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- 3. صفحة الإشعارات والتنبيهات -->
        <!-- ======================================================== -->
        <section id="page-notifications" class="page-tab space-y-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-900">سجل الإشعارات والتنبيهات</h2>
                        <p class="text-xs text-slate-500 mt-0.5">متابعة إشعارات تأكيد وشحن الطلبات وتحديثات الحساب</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="markAllNotificationsRead()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition">تحديد الكل كمقروء</button>
                        <button onclick="loadNotificationsList()" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold rounded-lg text-xs transition">تحديث</button>
                    </div>
                </div>
                <div id="notificationsContainer" class="space-y-3">
                    <!-- الإشعارات -->
                </div>
            </div>
        </section>

    </main>

    <!-- نافذة تسجيل الدخول الواقعية (Realistic Login Modal) -->
    <div id="modalLogin" class="app-modal fixed inset-0 bg-black/50 z-50 items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 sm:p-8 border border-slate-200 shadow-2xl relative">
            <button onclick="closeLoginModal()" class="absolute top-4 left-4 text-slate-400 hover:text-slate-700 text-lg font-bold">✕</button>
            
            <div class="text-center mb-6">
                <h3 class="text-lg font-black text-slate-900">تسجيل الدخول إلى حسابك</h3>
                <p class="text-xs text-slate-500 mt-1">أدخل بريدك الإلكتروني وكلمة المرور للمتابعة</p>
            </div>

            <form onsubmit="handleLoginFormSubmit(event)" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">البريد الإلكتروني</label>
                    <input type="email" id="inputLoginEmail" required placeholder="name@example.com" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none transition font-sans text-xs">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">كلمة المرور</label>
                    <input type="password" id="inputLoginPassword" required placeholder="••••••••" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none transition font-sans text-xs">
                </div>

                <div class="flex items-center justify-between text-[11px] text-slate-500">
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" id="checkRememberMe" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span>تذكر بيانات دخولي</span>
                    </label>
                </div>

                <button type="submit" id="btnLoginSubmit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold transition shadow-md shadow-blue-600/20 text-xs">
                    تسجيل الدخول
                </button>
            </form>
        </div>
    </div>

    <!-- نافذة منبثقة: الملف الشخصي وإدارة الجلسة -->
    <div id="modalUserProfile" class="app-modal fixed inset-0 bg-black/50 z-50 items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-sm w-full p-6 border border-slate-200 shadow-2xl space-y-4 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h4 class="font-extrabold text-sm text-slate-900">الملف الشخصي</h4>
                <button onclick="closeProfileModal()" class="text-slate-400 hover:text-slate-700 font-bold">✕</button>
            </div>
            
            <div class="space-y-3">
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 flex items-center gap-3">
                    <div id="profileAvatarLarge" class="w-10 h-10 rounded-full bg-blue-600 text-white font-black text-sm flex items-center justify-center">
                        A
                    </div>
                    <div>
                        <span id="profileDisplayName" class="font-bold text-slate-900 text-sm block"></span>
                        <span id="profileDisplayRole" class="text-[11px] text-slate-500 font-semibold block"></span>
                    </div>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-slate-400 block text-[11px] mb-0.5">البريد الإلكتروني:</span>
                    <span id="profileDisplayEmail" class="font-mono text-slate-900 font-medium"></span>
                </div>
            </div>

            <div class="pt-2 flex flex-col gap-2">
                <button onclick="switchTab('orders'); closeProfileModal();" class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg font-bold transition">
                    عرض طلباتي
                </button>
                <button onclick="logoutUser()" class="w-full py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg font-bold transition border border-rose-200">
                    تسجيل الخروج
                </button>
            </div>
        </div>
    </div>

    <!-- درج سلة المشتريات (Cart Drawer) -->
    <div id="cartDrawer" class="app-modal fixed inset-0 bg-black/40 z-50 justify-end p-0">
        <div class="bg-white w-full max-w-md h-full flex flex-col shadow-2xl">
            <div class="p-4 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <h3 class="font-extrabold text-sm text-slate-900">سلة المشتريات</h3>
                </div>
                <button onclick="closeCartDrawer()" class="p-1 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 font-bold">✕</button>
            </div>

            <!-- عناصر السلة -->
            <div id="cartItemsContainer" class="flex-1 overflow-y-auto p-4 space-y-3">
                <!-- عناصر السلة -->
            </div>

            <!-- الحساب والدفع -->
            <div class="p-4 border-t border-slate-200 bg-slate-50 space-y-3">
                <div class="flex items-center justify-between text-xs text-slate-600">
                    <span>المجموع الفرعي:</span>
                    <span id="cartSubtotal" class="font-bold text-slate-900">$0.00</span>
                </div>
                <div class="flex items-center justify-between text-xs text-slate-600">
                    <span>رسوم الشحن:</span>
                    <span class="text-emerald-600 font-bold">مجاني</span>
                </div>
                <div class="flex items-center justify-between text-sm font-extrabold text-slate-900 pt-2 border-t border-slate-200">
                    <span>المجموع الإجمالي:</span>
                    <span id="cartGrandTotal" class="text-blue-600 font-black">$0.00</span>
                </div>
                <button onclick="checkoutCart()" id="btnSubmitCart" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-md disabled:opacity-50">
                    إتمام الطلب وتأكيد الشراء
                </button>
            </div>
        </div>
    </div>

    <!-- نافذة منبثقة: إضافة منتج جديد (صلاحية المشرف فقط) -->
    <div id="modalCreateProduct" class="app-modal fixed inset-0 bg-black/40 z-50 items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 border border-slate-200 shadow-2xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h4 class="font-extrabold text-sm text-slate-900">إضافة منتج جديد</h4>
                <button onclick="closeProductModal()" class="text-slate-400 hover:text-slate-700 font-bold">✕</button>
            </div>
            <form onsubmit="submitProductForm(event)" class="mt-4 space-y-3.5 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">اسم المنتج</label>
                    <input type="text" id="formProductName" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">الوصف</label>
                    <textarea id="formProductDesc" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">السعر ($)</label>
                        <input type="number" step="0.01" id="formProductPrice" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">الكمية في المخزون</label>
                        <input type="number" id="formProductStock" required min="0" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none font-bold">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">التصنيف</label>
                    <select id="formProductCategory" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                        <option value="إضاءة">إضاءة</option>
                        <option value="إلكترونيات">إلكترونيات</option>
                        <option value="أثاث">أثاث</option>
                        <option value="منزل">منزل</option>
                    </select>
                </div>
                <div class="pt-3 flex gap-2">
                    <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold transition shadow-xs">حفظ المنتج</button>
                    <button type="button" onclick="closeProductModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة منبثقة: الشراء السريع -->
    <div id="modalInstantCheckout" class="app-modal fixed inset-0 bg-black/40 z-50 items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-sm w-full p-5 border border-slate-200 shadow-2xl text-xs space-y-4">
            <h4 class="font-extrabold text-sm text-slate-900 pb-3 border-b border-slate-100">تأكيد طلب الشراء</h4>
            
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                <div id="instantProdTitle" class="font-extrabold text-sm text-slate-900"></div>
                <div id="instantProdPrice" class="text-blue-600 font-black"></div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">الكمية المطلوبة:</label>
                <input type="number" id="instantProdQty" value="1" min="1" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none font-bold text-sm">
            </div>

            <div class="text-slate-500 bg-slate-50 p-2.5 rounded-lg text-[11px] flex items-center justify-between">
                <span>المخزون المتوفر:</span>
                <span id="instantProdAvailable" class="font-extrabold text-slate-900"></span>
            </div>

            <div class="pt-2 flex gap-2">
                <button onclick="confirmInstantOrder()" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold transition shadow-xs">تأكيد الشراء</button>
                <button onclick="closeInstantCheckoutModal()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold">إلغاء</button>
            </div>
        </div>
    </div>

    <!-- نافذة منبثقة: واجهة عرض تفاصيل الطلب (Order Details View) -->
    <div id="modalOrderDetails" class="app-modal fixed inset-0 bg-black/50 z-50 items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-xl w-full max-h-[90vh] flex flex-col border border-slate-200 shadow-2xl overflow-hidden text-xs">
            <!-- الهيدر -->
            <div class="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50/70">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 id="orderDetailsId" class="text-base font-black text-slate-900 font-mono">طلب #--</h3>
                            <span id="orderDetailsStatusBadge" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border bg-blue-50 text-blue-700 border-blue-200"></span>
                        </div>
                        <p id="orderDetailsDate" class="text-[11px] text-slate-500 mt-0.5 font-medium"></p>
                    </div>
                </div>
                <button onclick="closeOrderDetailsModal()" class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 font-bold transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- جسم النافذة (محتوى تفاصيل الطلب) -->
            <div class="p-4 sm:p-6 overflow-y-auto space-y-5 flex-1">
                <!-- شريط تتبع ومسار معالجة الطلب اللحظي -->
                <div id="orderStatusTracker" class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                    <!-- يتم بناؤه وتحديثه ديناميكياً حسب حالة الطلب -->
                </div>

                <!-- جدول قائمة المنتجات المحجوزة في الطلب -->
                <div>
                    <h4 class="font-extrabold text-xs text-slate-900 mb-2.5 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <span>المنتجات المطلوبة</span>
                    </h4>
                    <div class="border border-slate-200 rounded-xl overflow-hidden">
                        <table class="w-full text-right text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                <tr>
                                    <th class="p-3">اسم المنتج</th>
                                    <th class="p-3 text-center">الكمية</th>
                                    <th class="p-3 text-left">سعر القطعة</th>
                                    <th class="p-3 text-left">الإجمالي الفرعي</th>
                                </tr>
                            </thead>
                            <tbody id="orderDetailsItemsList" class="divide-y divide-slate-100 bg-white">
                                <!-- تملأ بالمنتجات والأسعار -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- معلومات الشحن والملخص المالي -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <!-- معلومات الشحن والاستلام -->
                    <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/70 space-y-2 text-slate-600">
                        <span class="font-bold text-slate-900 block text-xs">معلومات الشحن والاستلام</span>
                        <div class="flex items-center justify-between text-[11px]">
                            <span>طريقة التوصيل:</span>
                            <span class="font-bold text-slate-800">شحن سريع ومجاني</span>
                        </div>
                        <div class="flex items-center justify-between text-[11px]">
                            <span>طريقة الدفع:</span>
                            <span class="font-bold text-slate-800">الدفع نقداً عند الاستلام</span>
                        </div>
                        <div class="flex items-center justify-between text-[11px]">
                            <span>الضمان:</span>
                            <span class="font-bold text-emerald-700">منتجات أصلية 100% مع ضمان استبدال</span>
                        </div>
                    </div>

                    <!-- الملخص المالي -->
                    <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/70 space-y-2">
                        <span class="font-bold text-slate-900 block text-xs">الملخص المالي للطلب</span>
                        <div class="flex items-center justify-between text-[11px] text-slate-600">
                            <span>مجموع المنتجات:</span>
                            <span id="orderDetailsSubtotal" class="font-mono font-bold text-slate-800">$0.00</span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-600">
                            <span>رسوم الشحن:</span>
                            <span class="text-emerald-600 font-bold">مجاني</span>
                        </div>
                        <div class="flex items-center justify-between text-xs font-black text-slate-900 pt-2 border-t border-slate-200">
                            <span>المبلغ الإجمالي المستحق:</span>
                            <span id="orderDetailsGrandTotal" class="font-mono text-blue-600 text-sm font-black">$0.00</span>
                        </div>
                    </div>
                </div>

                <!-- أزرار تحكم المشرف (تظهر فقط للمشرف لتعديل حالة الطلب مباشرة) -->
                <div id="orderDetailsAdminActions" class="hidden p-3.5 rounded-xl border border-amber-200 bg-amber-50 space-y-2">
                    <span class="font-bold text-amber-900 block text-xs">إدارة حالة الطلب (صلاحية المشرف):</span>
                    <div id="orderDetailsAdminButtons" class="flex items-center gap-2 flex-wrap">
                        <!-- تملأ برمجياً -->
                    </div>
                </div>
            </div>

            <!-- الفوتر وإجراءات الطباعة والإغلاق -->
            <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between gap-3">
                <button onclick="printOrderDetails()" class="px-3.5 py-2 rounded-lg border border-slate-300 hover:bg-slate-100 text-slate-700 font-bold transition flex items-center gap-1.5 text-xs">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>طباعة الفاتورة</span>
                </button>
                <button onclick="closeOrderDetailsModal()" class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-lg font-bold transition text-xs shadow-xs">
                    إغلاق
                </button>
            </div>
        </div>
    </div>

    <!-- تذييل الصفحة (Footer) -->
    <footer class="mt-auto py-8 bg-white border-t border-slate-200 text-slate-600 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-900 text-sm">سوق التقنية</span>
                    <span class="text-slate-300">|</span>
                    <span class="text-slate-500">متجرك الإلكتروني المفضل</span>
                </div>
                <div class="flex items-center gap-6 text-slate-500 font-medium">
                    <a href="#" class="hover:text-blue-600 transition">الشروط والأحكام</a>
                    <a href="#" class="hover:text-blue-600 transition">سياسة الخصوصية</a>
                    <a href="#" class="hover:text-blue-600 transition">خدمة العملاء</a>
                    <a href="#" class="hover:text-blue-600 transition">الضمان والاسترجاع</a>
                </div>
                <p class="text-slate-400">© 2026 سوق التقنية. جميع الحقوق محفوظة.</p>
            </div>
        </div>
    </footer>

    <!-- منطق العمل والاتصال بخدمات المتجر -->
    <script>
        const API_PREFIX = '/api/v1';
        
        let sessionUser = {
            token: '',
            email: '',
            name: '',
            role: 'guest'
        };

        let currentActiveCategory = '';
        let currentCatalogPage = 1;
        let instantCheckoutProduct = null;
        let cartItems = [];

        // التهيئة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', async () => {
            switchTab('catalog');

            const cachedToken = localStorage.getItem('store_token');
            const cachedEmail = localStorage.getItem('store_email');

            if (cachedToken && cachedEmail) {
                sessionUser.token = cachedToken;
                sessionUser.email = cachedEmail;
                sessionUser.name = localStorage.getItem('store_name') || 'مستخدم';
                sessionUser.role = localStorage.getItem('store_role') || 'customer';
                updateAuthInterface();
            } else {
                // الوضع الافتراضي عند عدم وجود جلسة محفوظة
                sessionUser = { token: '', email: '', name: '', role: 'guest' };
                updateAuthInterface();
            }

            loadCatalogProducts();
            if (sessionUser.token) {
                loadNotificationsList();
            }
            renderCartDrawer();
        });

        // التبديل بين الأقسام
        function switchTab(tabId) {
            if (tabId === 'orders' && !sessionUser.token) {
                showToastNotification('يرجى تسجيل الدخول أولاً لاستعراض طلباتك', 'info');
                openLoginModal();
                return;
            }

            if (tabId === 'notifications' && !sessionUser.token) {
                showToastNotification('يرجى تسجيل الدخول لمشاهدة الإشعارات', 'info');
                openLoginModal();
                return;
            }

            document.querySelectorAll('.page-tab').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.classList.remove('active');
                btn.classList.add('text-slate-600');
            });

            const targetSection = document.getElementById(`page-${tabId}`);
            if (targetSection) targetSection.classList.add('active');

            const targetBtn = document.getElementById(`nav-${tabId}`);
            if (targetBtn) {
                targetBtn.classList.add('active');
                targetBtn.classList.remove('text-slate-600');
            }

            if (tabId === 'orders') loadOrdersData();
            if (tabId === 'notifications') loadNotificationsList();
        }

        // ========================================================
        // إدارة الحساب والمصادقة (Authentication Flow)
        // ========================================================
        function handleApiError(res, data, defaultMsg = 'حدث خطأ، يرجى المحاولة مرة أخرى') {
            const err = data?.error || {};
            const code = err.code || '';
            const msg = err.message || data?.message || '';
            const details = err.details;

            // 0. بيانات الدخول غير صحيحة (INVALID_CREDENTIALS)
            if (code === 'INVALID_CREDENTIALS') {
                showToastNotification('البريد الإلكتروني أو كلمة المرور غير صحيحة', 'error');
                return;
            }

            // 1. انتهاء الجلسة أو طلب تسجيل الدخول (401 UNAUTHENTICATED)
            if (res.status === 401 || code === 'UNAUTHENTICATED') {
                showToastNotification('انتهت الجلسة، يرجى تسجيل الدخول للمتابعة', 'info');
                logoutUser();
                openLoginModal();
                return;
            }

            // 2. تقييد الصلاحيات (403 FORBIDDEN)
            if (res.status === 403 || code === 'FORBIDDEN') {
                showToastNotification('عذراً، هذا الإجراء غير متاح لحسابك', 'error');
                return;
            }

            // 3. العنصر غير موجود (404 NOT_FOUND)
            if (res.status === 404 || code === 'NOT_FOUND') {
                showToastNotification('العنصر المطلوب غير متوفر أو تم حذفه', 'error');
                return;
            }

            // 4. تعارض البيانات ونقص المخزون والتزامن (409 CONFLICT)
            if (res.status === 409) {
                if (code === 'INSUFFICIENT_STOCK') {
                    showToastNotification('عذراً، نفدت الكمية المطلوبة من المخزون', 'error');
                    loadCatalogProducts(currentActiveCategory);
                    return;
                }
                if (code === 'RESERVATION_CONFLICT') {
                    showToastNotification('تم حجز القطعة الأخيرة قبل قليل من قبل مشترٍ آخر', 'error');
                    loadCatalogProducts(currentActiveCategory);
                    return;
                }
                if (code === 'INVALID_STATUS_TRANSITION') {
                    showToastNotification('لا يمكن إلغاء الطلب بعد أن تم شحنه', 'error');
                    return;
                }
                showToastNotification('تعذر إتمام العملية بسبب تعارض مؤقت، يرجى المحاولة مجدداً', 'error');
                loadCatalogProducts(currentActiveCategory);
                return;
            }

            // 5. التحقق من صحة المدخلات (422 VALIDATION_FAILED)
            if (res.status === 422 || code === 'VALIDATION_FAILED') {
                let detailText = '';
                if (details && typeof details === 'object') {
                    const firstList = Object.values(details)[0];
                    if (Array.isArray(firstList) && firstList.length > 0) {
                        const rawDetail = firstList[0];
                        if (rawDetail.includes('email')) detailText = 'البريد الإلكتروني المدخل غير صالح';
                        else if (rawDetail.includes('password')) detailText = 'كلمة المرور غير صحيحة';
                        else if (rawDetail.includes('price')) detailText = 'يرجى إدخال سعر صحيح للمنتج';
                        else if (rawDetail.includes('quantity')) detailText = 'يرجى تحديد كمية صحيحة';
                        else if (rawDetail.includes('stock')) detailText = 'يرجى إدخال كمية مخزون صحيحة';
                        else if (rawDetail.includes('name')) detailText = 'يرجى إدخال اسم المنتج';
                        else detailText = rawDetail;
                    }
                }
                showToastNotification(detailText || 'يرجى التأكد من صحة البيانات المدخلة', 'error');
                return;
            }

            // 6. تجاوز عدد المحاولات (429 TOO_MANY_REQUESTS)
            if (res.status === 429 || code === 'TOO_MANY_REQUESTS') {
                showToastNotification('محاولات متكررة، يرجى الانتظار دقيقة قبل المحاولة مجدداً', 'error');
                return;
            }

            // 7. ضغط أو تعطل خدمة المخزون (503 INVENTORY_UNAVAILABLE)
            if (res.status === 503 || code === 'INVENTORY_UNAVAILABLE') {
                showToastNotification('المتجر يواجه ضغطاً مؤقتاً، يرجى إعادة المحاولة بعد لحظات', 'error');
                return;
            }

            // 8. أخطاء الخادم العامة (500 INTERNAL_ERROR)
            if (res.status >= 500) {
                showToastNotification('حدث خطأ غير متوقع، يرجى المحاولة لاحقاً', 'error');
                return;
            }

            // الافتراضي
            showToastNotification(defaultMsg, 'error');
        }

        function openLoginModal() {
            document.getElementById('modalLogin').classList.add('active');
        }

        function closeLoginModal() {
            document.getElementById('modalLogin').classList.remove('active');
        }

        async function handleLoginFormSubmit(e) {
            e.preventDefault();
            const email = document.getElementById('inputLoginEmail').value.trim();
            const password = document.getElementById('inputLoginPassword').value;

            if (!email || !password) {
                showToastNotification('يرجى إدخال البريد الإلكتروني وكلمة المرور', 'error');
                return;
            }

            const submitBtn = document.getElementById('btnLoginSubmit');
            submitBtn.disabled = true;
            submitBtn.innerText = 'جاري التحقق...';

            try {
                const response = await fetch(`${API_PREFIX}/auth/login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ email, password })
                });

                const data = await response.json();
                if (response.ok) {
                    sessionUser = {
                        token: data.access_token,
                        email: data.user.email,
                        name: data.user.name,
                        role: data.user.role
                    };

                    localStorage.setItem('store_token', sessionUser.token);
                    localStorage.setItem('store_email', sessionUser.email);
                    localStorage.setItem('store_name', sessionUser.name);
                    localStorage.setItem('store_role', sessionUser.role);

                    closeLoginModal();
                    updateAuthInterface();
                    showToastNotification(`مرحباً بك مجدداً، ${sessionUser.name}`, 'success');
                    loadCatalogProducts(currentActiveCategory);
                    loadNotificationsList();
                } else {
                    handleApiError(response, data, 'فشل تسجيل الدخول، يرجى التأكد من البريد وكلمة المرور');
                }
            } catch (err) {
                showToastNotification('تعذر تسجيل الدخول حالياً، يرجى إعادة المحاولة', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = 'تسجيل الدخول';
            }
        }

        function logoutUser() {
            sessionUser = { token: '', email: '', name: '', role: 'guest' };
            localStorage.removeItem('store_token');
            localStorage.removeItem('store_email');
            localStorage.removeItem('store_name');
            localStorage.removeItem('store_role');

            closeProfileModal();
            updateAuthInterface();
            showToastNotification('تم تسجيل الخروج بنجاح', 'info');
            switchTab('catalog');
            loadCatalogProducts(currentActiveCategory);
        }

        function updateAuthInterface() {
            const container = document.getElementById('authContainer');
            const addProductBtn = document.getElementById('btnAdminAddProduct');

            if (!sessionUser.token) {
                // حالة الزائر غير المسجل
                container.innerHTML = `
                    <button onclick="openLoginModal()" class="flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                        <span>تسجيل الدخول</span>
                    </button>
                `;
                if (addProductBtn) addProductBtn.classList.add('hidden');
                return;
            }

            // حالة المستخدم المسجل
            const roleName = sessionUser.role === 'admin' ? 'مشرف' : 'زبون';
            const roleBadgeClass = sessionUser.role === 'admin' ? 'text-purple-700 bg-purple-50' : 'text-blue-700 bg-blue-50';

            container.innerHTML = `
                <button onclick="openProfileModal()" class="flex items-center gap-2 px-3 py-1.5 hover:bg-slate-100 rounded-lg border border-slate-200 bg-white transition">
                    <div class="w-7 h-7 rounded-full bg-blue-600 text-white font-black text-xs flex items-center justify-center">
                        ${sessionUser.name.charAt(0).toUpperCase()}
                    </div>
                    <div class="text-right hidden sm:block">
                        <span class="text-xs font-bold text-slate-900 block leading-tight">${sessionUser.name}</span>
                        <span class="text-[10px] font-semibold block leading-tight ${roleBadgeClass}">${roleName}</span>
                    </div>
                </button>
            `;

            // إظهار زر إضافة منتج جديد إذا كان مشرفاً
            if (sessionUser.role === 'admin') {
                if (addProductBtn) addProductBtn.classList.remove('hidden');
            } else {
                if (addProductBtn) addProductBtn.classList.add('hidden');
            }

            // تحديث بيانات الملف الشخصي
            document.getElementById('profileDisplayName').innerText = sessionUser.name;
            document.getElementById('profileDisplayEmail').innerText = sessionUser.email;
            document.getElementById('profileDisplayRole').innerText = sessionUser.role === 'admin' ? 'مشرف المتجر' : 'زبون معتمد';
            document.getElementById('profileAvatarLarge').innerText = sessionUser.name.charAt(0).toUpperCase();
        }

        // ========================================================
        // إدارة الكتالوج وعرض المنتجات
        // ========================================================
        async function loadCatalogProducts(category = '') {
            currentActiveCategory = category;
            try {
                let url = `${API_PREFIX}/products?page=${currentCatalogPage}&per_page=8`;
                if (category) url += `&category=${encodeURIComponent(category)}`;

                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();

                if (res.ok) {
                    renderCatalogCards(json.data || []);

                    if (json.meta) {
                        document.getElementById('pageStatsLabel').innerText = `الصفحة ${json.meta.current_page} من ${json.meta.last_page} (الإجمالي: ${json.meta.total} منتج)`;
                        document.getElementById('btnPagePrev').disabled = json.meta.current_page <= 1;
                        document.getElementById('btnPageNext').disabled = json.meta.current_page >= json.meta.last_page;
                    }
                } else {
                    handleApiError(res, json, 'تعذر تحميل المنتجات');
                    const container = document.getElementById('productsGridContainer');
                    if (res.status === 503) {
                        container.innerHTML = `
                            <div class="col-span-full bg-amber-50 border border-amber-200 p-8 rounded-2xl text-center space-y-2">
                                <svg class="w-8 h-8 text-amber-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <p class="font-bold text-amber-900 text-sm">تعذر تحميل قائمة المنتجات حالياً</p>
                                <p class="text-xs text-amber-700">نواجه ضغطاً مؤقتاً في تحديث بيانات المتجر، يرجى المحاولة بعد لحظات.</p>
                                <button onclick="loadCatalogProducts()" class="mt-2 px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg text-xs transition">إعادة المحاولة</button>
                            </div>
                        `;
                    }
                }
            } catch (err) {
                console.error(err);
            }
        }

        function filterByCategory(cat, btn) {
            currentCatalogPage = 1;
            document.querySelectorAll('.category-chip').forEach(b => {
                b.className = 'category-chip bg-slate-50 text-slate-700 hover:bg-slate-100 px-3.5 py-1.5 rounded-lg border border-slate-200 font-bold transition';
            });
            if (btn) {
                btn.className = 'category-chip active px-3.5 py-1.5 rounded-lg border border-slate-200 font-bold transition';
            }
            loadCatalogProducts(cat);
        }

        function navigateCatalogPage(step) {
            currentCatalogPage += step;
            loadCatalogProducts(currentActiveCategory);
        }

        function handleSearch(term) {
            const query = term.trim().toLowerCase();
            const cards = document.querySelectorAll('.product-item-card');
            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                card.style.display = text.includes(query) ? 'flex' : 'none';
            });
        }

        function renderCatalogCards(products) {
            const container = document.getElementById('productsGridContainer');
            container.innerHTML = '';

            if (products.length === 0) {
                container.innerHTML = '<div class="col-span-full bg-white p-12 rounded-2xl border border-slate-200 text-center text-slate-400 text-xs">لا توجد منتجات مسجلة في هذا التصنيف حالياً.</div>';
                return;
            }

            products.forEach(p => {
                let stockText = '';
                let stockPillClass = '';
                const isNullStock = p.stock === null || p.stock === undefined;
                const stock = isNullStock ? null : Number(p.stock);
                const isAvailable = stock !== null && stock > 0;

                if (isNullStock) {
                    stockText = 'غير متوفر';
                    stockPillClass = 'bg-slate-100 text-slate-700 border-slate-300';
                } else if (stock <= 0) {
                    stockText = 'نفد من المخزون';
                    stockPillClass = 'bg-rose-50 text-rose-700 border-rose-200';
                } else if (stock <= 3) {
                    stockText = `متبقي ${stock} فقط!`;
                    stockPillClass = 'bg-amber-50 text-amber-700 border-amber-200';
                } else {
                    stockText = `متوفر: ${stock} قطعة`;
                    stockPillClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                }

                const card = document.createElement('div');
                card.className = 'product-item-card bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col justify-between hover:border-slate-300 hover:shadow-lg transition duration-200 group';
                card.innerHTML = `
                    <div>
                        <div class="h-44 bg-gradient-to-tr from-slate-100 to-slate-50 border-b border-slate-100 flex items-center justify-center relative p-4 group-hover:scale-[1.02] transition duration-200">
                            <div class="w-20 h-20 rounded-2xl bg-white shadow-sm flex items-center justify-center text-slate-400 group-hover:text-blue-600 transition">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                            <span class="absolute top-3 right-3 text-[10px] font-bold px-2.5 py-1 rounded-md border ${stockPillClass}">
                                ${stockText}
                            </span>
                        </div>
                        <div class="p-5">
                            <span class="text-[10px] font-extrabold uppercase text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-100">${p.category}</span>
                            <h3 class="font-extrabold text-sm text-slate-900 mt-2 leading-snug">${p.name}</h3>
                            <p class="text-xs text-slate-500 mt-1.5 line-clamp-2 leading-relaxed">${p.description || 'لا يوجد وصف متاح للمنتج.'}</p>
                        </div>
                    </div>
                    <div class="p-5 pt-0 border-t border-slate-100 mt-2 flex flex-col gap-2.5">
                        <div class="flex items-center justify-between pt-3">
                            <span class="text-xs text-slate-400 font-medium">السعر:</span>
                            <span class="text-lg font-black text-slate-900 font-mono">$${parseFloat(p.price).toFixed(2)}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button onclick="addToCart(${JSON.stringify(p).replace(/"/g, '&quot;')})"
                                ${!isAvailable ? 'disabled' : ''}
                                class="py-2 px-3 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1 ${isAvailable ? 'bg-slate-100 hover:bg-slate-200 text-slate-800' : 'bg-slate-100 text-slate-400 cursor-not-allowed'}">
                                <span>+ السلة</span>
                            </button>
                            <button onclick="openInstantCheckout(${JSON.stringify(p).replace(/"/g, '&quot;')})"
                                ${!isAvailable ? 'disabled' : ''}
                                class="py-2 px-3 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1 ${isAvailable ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-xs' : 'bg-slate-200 text-slate-400 cursor-not-allowed'}">
                                <span>شراء سريع</span>
                            </button>
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        // ========================================================
        // إدارة سلة المشتريات
        // ========================================================
        function addToCart(product) {
            if (product.stock === null || product.stock === undefined || product.stock <= 0) {
                showToastNotification('عذراً، هذا المنتج غير متوفر حالياً', 'error');
                return;
            }

            const existing = cartItems.find(i => i.id === product.id);
            if (existing) {
                if (existing.qty < product.stock) {
                    existing.qty += 1;
                    showToastNotification(`تمت زيادة كمية: ${product.name}`, 'info');
                } else {
                    showToastNotification('تم الوصول للحد الأقصى المتوفر من هذا المنتج', 'error');
                }
            } else {
                cartItems.push({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.price),
                    stock: product.stock,
                    qty: 1
                });
                showToastNotification(`تمت إضافة ${product.name} إلى السلة`, 'success');
            }
            renderCartDrawer();
        }

        function updateCartQty(productId, delta) {
            const item = cartItems.find(i => i.id === productId);
            if (!item) return;

            item.qty += delta;
            if (item.qty <= 0) {
                cartItems = cartItems.filter(i => i.id !== productId);
            } else if (item.qty > item.stock) {
                item.qty = item.stock;
                showToastNotification('لا تتوفر كمية إضافية من هذا المنتج حالياً', 'error');
            }
            renderCartDrawer();
        }

        function renderCartDrawer() {
            const container = document.getElementById('cartItemsContainer');
            const countBadge = document.getElementById('cartCountBadge');
            const totalCount = cartItems.reduce((acc, cur) => acc + cur.qty, 0);
            countBadge.innerText = totalCount;

            container.innerHTML = '';
            if (cartItems.length === 0) {
                container.innerHTML = '<div class="py-16 text-center text-slate-400 text-xs">سلة المشتريات فارغة حالياً.</div>';
                document.getElementById('cartSubtotal').innerText = '$0.00';
                document.getElementById('cartGrandTotal').innerText = '$0.00';
                document.getElementById('btnSubmitCart').disabled = true;
                return;
            }

            document.getElementById('btnSubmitCart').disabled = false;
            let subtotal = 0;

            cartItems.forEach(item => {
                const itemTotal = item.price * item.qty;
                subtotal += itemTotal;

                const row = document.createElement('div');
                row.className = 'p-3 rounded-xl border border-slate-200 bg-white flex items-center justify-between text-xs shadow-2xs';
                row.innerHTML = `
                    <div class="flex-1 pr-2">
                        <div class="font-bold text-slate-900">${item.name}</div>
                        <div class="text-slate-500 font-mono text-[11px] mt-0.5">$${item.price.toFixed(2)} × ${item.qty}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-extrabold text-slate-900 font-mono pl-2">$${itemTotal.toFixed(2)}</span>
                        <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden bg-slate-50">
                            <button onclick="updateCartQty(${item.id}, -1)" class="px-2 py-1 hover:bg-slate-200 font-bold text-slate-600">-</button>
                            <span class="px-2 font-mono font-bold text-slate-800 text-[11px]">${item.qty}</span>
                            <button onclick="updateCartQty(${item.id}, 1)" class="px-2 py-1 hover:bg-slate-200 font-bold text-slate-600">+</button>
                        </div>
                    </div>
                `;
                container.appendChild(row);
            });

            document.getElementById('cartSubtotal').innerText = `$${subtotal.toFixed(2)}`;
            document.getElementById('cartGrandTotal').innerText = `$${subtotal.toFixed(2)}`;
        }

        function openCartDrawer() {
            document.getElementById('cartDrawer').classList.add('active');
        }

        function closeCartDrawer() {
            document.getElementById('cartDrawer').classList.remove('active');
        }

        async function checkoutCart() {
            if (cartItems.length === 0) return;

            if (!sessionUser.token) {
                showToastNotification('يرجى تسجيل الدخول أولاً لإتمام طلب الشراء', 'info');
                closeCartDrawer();
                openLoginModal();
                return;
            }

            const payloadItems = cartItems.map(i => ({
                product_id: i.id,
                quantity: i.qty
            }));

            try {
                const res = await fetch(`${API_PREFIX}/orders`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${sessionUser.token}`
                    },
                    body: JSON.stringify({ items: payloadItems })
                });

                const data = await res.json();
                if (res.ok) {
                    cartItems = [];
                    renderCartDrawer();
                    closeCartDrawer();
                    showToastNotification(`تم تأكيد الطلب بنجاح برقم #${data.order.id}`, 'success');
                    switchTab('orders');
                    loadCatalogProducts(currentActiveCategory);
                    openOrderDetails(data.order.id);
                } else {
                    handleApiError(res, data, 'فشل إتمام طلب الشراء');
                }
            } catch (err) {
                showToastNotification('تعذر تأكيد الطلب، يرجى المحاولة مرة أخرى', 'error');
            }
        }

        // ========================================================
        // الشراء السريع
        // ========================================================
        function openInstantCheckout(prod) {
            if (!sessionUser.token) {
                showToastNotification('يرجى تسجيل الدخول أولاً للشراء السريع', 'info');
                openLoginModal();
                return;
            }

            if (prod.stock === null || prod.stock === undefined || prod.stock <= 0) {
                showToastNotification('عذراً، هذا المنتج غير متوفر حالياً', 'error');
                return;
            }

            instantCheckoutProduct = prod;
            document.getElementById('instantProdTitle').innerText = prod.name;
            document.getElementById('instantProdPrice').innerText = `$${parseFloat(prod.price).toFixed(2)}`;
            document.getElementById('instantProdAvailable').innerText = (prod.stock !== null && prod.stock !== undefined) ? `${prod.stock} قطعة` : 'غير متوفر';
            document.getElementById('instantProdQty').value = 1;
            document.getElementById('instantProdQty').max = prod.stock || 1;
            document.getElementById('modalInstantCheckout').classList.add('active');
        }

        function closeInstantCheckoutModal() {
            document.getElementById('modalInstantCheckout').classList.remove('active');
        }

        async function confirmInstantOrder() {
            const qty = parseInt(document.getElementById('instantProdQty').value);
            if (!qty || qty < 1) {
                showToastNotification('يرجى تحديد كمية صحيحة', 'error');
                return;
            }

            try {
                const res = await fetch(`${API_PREFIX}/orders`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${sessionUser.token}`
                    },
                    body: JSON.stringify({
                        items: [{ product_id: instantCheckoutProduct.id, quantity: qty }]
                    })
                });

                const data = await res.json();
                if (res.ok) {
                    closeInstantCheckoutModal();
                    showToastNotification(`تم إنشاء الطلب بنجاح برقم #${data.order.id}`, 'success');
                    loadCatalogProducts(currentActiveCategory);
                    switchTab('orders');
                    openOrderDetails(data.order.id);
                } else {
                    handleApiError(res, data, 'فشل حجز وتأكيد طلب الشراء');
                }
            } catch (err) {
                showToastNotification('تعذر إتمام عملية الشراء، يرجى إعادة المحاولة', 'error');
            }
        }

        // ========================================================
        // إدارة ومتابعة الطلبات
        // ========================================================
        async function loadOrdersData() {
            if (!sessionUser.token) return;

            try {
                const res = await fetch(`${API_PREFIX}/orders`, {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${sessionUser.token}`
                    }
                });

                const json = await res.json();
                if (res.ok) {
                    renderOrdersTableRows(json.data || []);

                    if (sessionUser.role === 'admin') {
                        document.getElementById('ordersMainTitle').innerText = 'كافة طلبات المتجر (صلاحية المشرف)';
                        document.getElementById('ordersSubTitle').innerText = 'المشرف يملك الحق بتغيير حالة شحن أي طلب وتتبع مسار المعالجة';
                    } else {
                        document.getElementById('ordersMainTitle').innerText = 'سجل طلباتي الشخصية';
                        document.getElementById('ordersSubTitle').innerText = 'استعراض ومتابعة مسار معالجة طلباتك';
                    }
                } else {
                    handleApiError(res, json, 'تعذر تحميل سجل الطلبات');
                }
            } catch (err) {
                console.error(err);
                showToastNotification('تعذر تحديث سجل الطلبات حالياً', 'error');
            }
        }

        function renderOrdersTableRows(orders) {
            const tbody = document.getElementById('ordersListRows');
            tbody.innerHTML = '';

            if (orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-slate-400">لا توجد طلبات مسجلة حالياً.</td></tr>';
                return;
            }

            const statusBadges = {
                'pending': 'bg-blue-50 text-blue-700 border-blue-200',
                'placed': 'bg-blue-50 text-blue-700 border-blue-200',
                'confirmed': 'bg-blue-50 text-blue-700 border-blue-200',
                'shipped': 'bg-amber-50 text-amber-700 border-amber-200',
                'delivered': 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'cancelled': 'bg-rose-50 text-rose-700 border-rose-200',
                'failed': 'bg-rose-50 text-rose-700 border-rose-200',
                'rejected': 'bg-rose-50 text-rose-700 border-rose-200'
            };

            const statusNames = {
                'pending': 'قيد الانتظار',
                'placed': 'تم الإرسال',
                'confirmed': 'مؤكد',
                'shipped': 'قيد الشحن',
                'delivered': 'تم التسليم',
                'cancelled': 'ملغي',
                'failed': 'فشل المعالجة',
                'rejected': 'مرفوض'
            };

            orders.forEach(order => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50 transition border-b border-slate-100';
                const itemsSummary = (order.items || []).map(i => `${i.name} (${i.quantity})`).join('، ');

                let actionHtml = '';
                if (sessionUser.role === 'admin') {
                    actionHtml = `
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button onclick="openOrderDetails(${order.id})" class="px-2.5 py-1 rounded-md bg-blue-50 hover:bg-blue-100 text-blue-700 text-[11px] font-bold border border-blue-200 transition">
                                عرض الطلب
                            </button>
                            ${(order.status === 'placed' || order.status === 'confirmed') ? `
                                <button onclick="requestOrderStatusUpdate(${order.id}, 'shipped')" class="px-2.5 py-1 rounded bg-amber-50 hover:bg-amber-100 text-amber-800 text-[11px] font-bold border border-amber-200 transition">شحن</button>
                                <button onclick="requestOrderStatusUpdate(${order.id}, 'cancelled')" class="px-2.5 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-800 text-[11px] font-bold border border-rose-200 transition">إلغاء</button>
                            ` : ''}
                            ${order.status === 'shipped' ? `
                                <button onclick="requestOrderStatusUpdate(${order.id}, 'delivered')" class="px-2.5 py-1 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-[11px] font-bold border border-emerald-200 transition">تسليم</button>
                            ` : ''}
                        </div>
                    `;
                } else {
                    // للزبون: إجراء عرض تفاصيل الطلب والفاتورة
                    actionHtml = `
                        <button onclick="openOrderDetails(${order.id})" class="px-2.5 py-1 rounded-md bg-blue-50 hover:bg-blue-100 text-blue-700 text-[11px] font-bold border border-blue-200 transition flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <span>عرض الطلب</span>
                        </button>
                    `;
                }

                tr.innerHTML = `
                    <td class="p-3.5 font-mono font-bold">
                        <button onclick="openOrderDetails(${order.id})" class="text-blue-600 hover:text-blue-800 hover:underline">#${order.id}</button>
                    </td>
                    <td class="p-3.5 text-slate-700 max-w-xs">${itemsSummary}</td>
                    <td class="p-3.5 font-bold text-slate-900 font-mono">$${parseFloat(order.total).toFixed(2)}</td>
                    <td class="p-3.5">
                        <span class="px-2.5 py-1 rounded-md text-[10px] font-bold border ${statusBadges[order.status] || 'bg-slate-100'}">
                            ${statusNames[order.status] || order.status}
                        </span>
                    </td>
                    <td class="p-3.5 text-slate-500 text-[11px] font-mono">${order.created_at ? new Date(order.created_at).toLocaleDateString('ar-SY') : '-'}</td>
                    <td class="p-3.5">${actionHtml}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        // ========================================================
        // واجهة عرض وتفاصيل الطلب (Show Order Details)
        // ========================================================
        async function openOrderDetails(orderId) {
            if (!sessionUser.token) {
                showToastNotification('يرجى تسجيل الدخول أولاً لعرض تفاصيل الطلب', 'info');
                openLoginModal();
                return;
            }

            try {
                const res = await fetch(`${API_PREFIX}/orders/${orderId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${sessionUser.token}`
                    }
                });

                const json = await res.json();
                if (!res.ok) {
                    handleApiError(res, json, 'تعذر جلب تفاصيل الطلب');
                    return;
                }

                const order = json.order;

                // 1. العنوان وتاريخ الإنشاء
                document.getElementById('orderDetailsId').innerText = `طلب #${order.id}`;
                document.getElementById('orderDetailsDate').innerText = order.created_at ? new Date(order.created_at).toLocaleString('ar-SY', { dateStyle: 'full', timeStyle: 'short' }) : '-';

                // 2. شارة الحالة
                const statusBadges = {
                    'pending': 'bg-blue-50 text-blue-700 border-blue-200',
                    'placed': 'bg-blue-50 text-blue-700 border-blue-200',
                    'confirmed': 'bg-blue-50 text-blue-700 border-blue-200',
                    'shipped': 'bg-amber-50 text-amber-700 border-amber-200',
                    'delivered': 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'cancelled': 'bg-rose-50 text-rose-700 border-rose-200',
                    'failed': 'bg-rose-50 text-rose-700 border-rose-200',
                    'rejected': 'bg-rose-50 text-rose-700 border-rose-200'
                };
                const statusNames = {
                    'pending': 'قيد الانتظار',
                    'placed': 'تم الإرسال',
                    'confirmed': 'مؤكد',
                    'shipped': 'قيد الشحن',
                    'delivered': 'تم التسليم بنجاح',
                    'cancelled': 'ملغي',
                    'failed': 'فشل المعالجة',
                    'rejected': 'مرفوض'
                };

                const badgeEl = document.getElementById('orderDetailsStatusBadge');
                badgeEl.className = `px-2.5 py-0.5 rounded-full text-[10px] font-bold border ${statusBadges[order.status] || 'bg-slate-100'}`;
                badgeEl.innerText = statusNames[order.status] || order.status;

                // 3. مسار معالجة الطلب (Stepper Tracker)
                renderOrderTracker(order.status);

                // 4. جدول المنتجات
                const itemsTbody = document.getElementById('orderDetailsItemsList');
                itemsTbody.innerHTML = '';
                (order.items || []).forEach(item => {
                    const tr = document.createElement('tr');
                    const unitPrice = parseFloat(item.unit_price || 0);
                    const itemTotal = unitPrice * item.quantity;
                    tr.className = 'hover:bg-slate-50 transition border-b border-slate-100';
                    tr.innerHTML = `
                        <td class="p-3 font-bold text-slate-800">${item.name}</td>
                        <td class="p-3 text-center font-mono font-bold text-slate-700">${item.quantity}</td>
                        <td class="p-3 text-left font-mono text-slate-600">$${unitPrice.toFixed(2)}</td>
                        <td class="p-3 text-left font-mono font-bold text-slate-900">$${itemTotal.toFixed(2)}</td>
                    `;
                    itemsTbody.appendChild(tr);
                });

                // 5. الملخص المالي
                const totalFloat = parseFloat(order.total || 0);
                document.getElementById('orderDetailsSubtotal').innerText = `$${totalFloat.toFixed(2)}`;
                document.getElementById('orderDetailsGrandTotal').innerText = `$${totalFloat.toFixed(2)}`;

                // 6. لوحة أزرار المشرف لتغيير الحالة
                const adminSection = document.getElementById('orderDetailsAdminActions');
                const adminButtons = document.getElementById('orderDetailsAdminButtons');
                adminButtons.innerHTML = '';

                if (sessionUser.role === 'admin') {
                    adminSection.classList.remove('hidden');
                    if (order.status === 'placed' || order.status === 'confirmed') {
                        adminButtons.innerHTML = `
                            <button onclick="requestStatusAndUpdateView(${order.id}, 'shipped')" class="px-3.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs transition shadow-xs">
                                شحن هذا الطلب
                            </button>
                            <button onclick="requestStatusAndUpdateView(${order.id}, 'cancelled')" class="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs transition shadow-xs">
                                إلغاء الطلب
                            </button>
                        `;
                    } else if (order.status === 'shipped') {
                        adminButtons.innerHTML = `
                            <button onclick="requestStatusAndUpdateView(${order.id}, 'delivered')" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-xs">
                                تأكيد تسليم الشحنة
                            </button>
                        `;
                    } else {
                        adminButtons.innerHTML = `<span class="text-slate-500 text-xs">اكتملت معالجة هذا الطلب (${statusNames[order.status]}).</span>`;
                    }
                } else {
                    adminSection.classList.add('hidden');
                }

                // فتح النافذة
                document.getElementById('modalOrderDetails').classList.add('active');
            } catch (err) {
                console.error(err);
                showToastNotification('تعذر الاتصال بالخادم لعرض تفاصيل الطلب', 'error');
            }
        }

        function closeOrderDetailsModal() {
            document.getElementById('modalOrderDetails').classList.remove('active');
        }

        async function requestStatusAndUpdateView(orderId, newStatus) {
            await requestOrderStatusUpdate(orderId, newStatus);
            openOrderDetails(orderId);
        }

        function renderOrderTracker(status) {
            const trackerBox = document.getElementById('orderStatusTracker');

            if (status === 'cancelled' || status === 'failed' || status === 'rejected') {
                let noteTitle = 'تم إلغاء هذا الطلب';
                let noteDesc = 'تم إلغاء الطلب رسمياً وإعادة الكميات المحجوزة للمخزون.';
                if (status === 'failed') {
                    noteTitle = 'تعذر استكمال معالجة الطلب';
                    noteDesc = 'حدث تعثر أثناء تأكيد المعاملة، وتم إلغاء حجز الكميات تلقائياً.';
                } else if (status === 'rejected') {
                    noteTitle = 'تم رفض الطلب';
                    noteDesc = 'تم رفض الطلب نظراً لعدم توفر الكميات المطلوبة في المخزون.';
                }

                trackerBox.className = 'p-4 bg-rose-50 rounded-xl border border-rose-200 space-y-2';
                trackerBox.innerHTML = `
                    <div class="flex items-center gap-2 text-rose-800 font-bold text-xs">
                        <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>${noteTitle}</span>
                    </div>
                    <p class="text-[11px] text-rose-700 leading-relaxed">${noteDesc}</p>
                `;
                return;
            }

            trackerBox.className = 'p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3';
            trackerBox.innerHTML = `
                <div class="text-[11px] font-bold text-slate-500 flex items-center justify-between">
                    <span>مسار معالجة الطلب:</span>
                    <span id="orderTrackerCurrentStepText" class="text-blue-700 font-extrabold"></span>
                </div>
                <div class="grid grid-cols-4 gap-2 text-center text-[10px] font-semibold relative">
                    <div id="step-confirmed" class="flex flex-col items-center gap-1.5">
                        <div class="step-circle w-6 h-6 rounded-full flex items-center justify-center font-bold text-[11px] bg-slate-200 text-slate-600 transition">1</div>
                        <span class="text-slate-600">تم التأكيد</span>
                    </div>
                    <div id="step-processing" class="flex flex-col items-center gap-1.5">
                        <div class="step-circle w-6 h-6 rounded-full flex items-center justify-center font-bold text-[11px] bg-slate-200 text-slate-600 transition">2</div>
                        <span class="text-slate-600">قيد التجهيز</span>
                    </div>
                    <div id="step-shipped" class="flex flex-col items-center gap-1.5">
                        <div class="step-circle w-6 h-6 rounded-full flex items-center justify-center font-bold text-[11px] bg-slate-200 text-slate-600 transition">3</div>
                        <span class="text-slate-600">قيد الشحن</span>
                    </div>
                    <div id="step-delivered" class="flex flex-col items-center gap-1.5">
                        <div class="step-circle w-6 h-6 rounded-full flex items-center justify-center font-bold text-[11px] bg-slate-200 text-slate-600 transition">4</div>
                        <span class="text-slate-600">تم التسليم</span>
                    </div>
                </div>
            `;

            let activeIndex = 1;
            if (status === 'placed' || status === 'pending') {
                activeIndex = 0;
                document.getElementById('orderTrackerCurrentStepText').innerText = 'تم استلام الطلب وتأكيده';
            } else if (status === 'confirmed') {
                activeIndex = 1;
                document.getElementById('orderTrackerCurrentStepText').innerText = 'جاري التجهيز والتعبئة';
            } else if (status === 'shipped') {
                activeIndex = 2;
                document.getElementById('orderTrackerCurrentStepText').innerText = 'في الطريق إليك مع شركة الشحن';
            } else if (status === 'delivered') {
                activeIndex = 3;
                document.getElementById('orderTrackerCurrentStepText').innerText = 'تم تسليم الشحنة بنجاح';
            }

            const stepIds = ['step-confirmed', 'step-processing', 'step-shipped', 'step-delivered'];
            stepIds.forEach((id, idx) => {
                const el = document.getElementById(id);
                if (!el) return;
                const circle = el.querySelector('.step-circle');
                const label = el.querySelector('span');
                if (idx < activeIndex) {
                    circle.className = 'step-circle w-6 h-6 rounded-full flex items-center justify-center font-bold text-[11px] bg-emerald-500 text-white shadow-2xs';
                    circle.innerText = '✓';
                    label.className = 'text-emerald-700 font-bold';
                } else if (idx === activeIndex) {
                    circle.className = 'step-circle w-6 h-6 rounded-full flex items-center justify-center font-bold text-[11px] bg-blue-600 text-white ring-4 ring-blue-100 shadow-xs';
                    circle.innerText = `${idx + 1}`;
                    label.className = 'text-blue-700 font-black';
                } else {
                    circle.className = 'step-circle w-6 h-6 rounded-full flex items-center justify-center font-bold text-[11px] bg-slate-200 text-slate-400';
                    circle.innerText = `${idx + 1}`;
                    label.className = 'text-slate-400';
                }
            });
        }

        function printOrderDetails() {
            window.print();
        }

        async function requestOrderStatusUpdate(orderId, newStatus) {
            try {
                const res = await fetch(`${API_PREFIX}/orders/${orderId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${sessionUser.token}`
                    },
                    body: JSON.stringify({ status: newStatus })
                });

                const data = await res.json();
                if (res.ok) {
                    showToastNotification(`تم تحديث حالة الطلب #${orderId} بنجاح`, 'success');
                    loadOrdersData();
                } else {
                    handleApiError(res, data, 'تعذر تحديث حالة الطلب');
                }
            } catch (err) {
                showToastNotification('تعذر تحديث الطلب، يرجى إعادة المحاولة', 'error');
            }
        }

        // ========================================================
        // إضافة منتج جديد (المشرف فقط)
        // ========================================================
        function openProductModal() {
            document.getElementById('modalCreateProduct').classList.add('active');
        }

        function closeProductModal() {
            document.getElementById('modalCreateProduct').classList.remove('active');
        }

        async function submitProductForm(e) {
            e.preventDefault();
            const body = {
                name: document.getElementById('formProductName').value,
                description: document.getElementById('formProductDesc').value,
                price: parseFloat(document.getElementById('formProductPrice').value),
                stock: parseInt(document.getElementById('formProductStock').value),
                category: document.getElementById('formProductCategory').value
            };

            try {
                const res = await fetch(`${API_PREFIX}/products`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${sessionUser.token}`
                    },
                    body: JSON.stringify(body)
                });

                const json = await res.json();
                if (res.ok) {
                    closeProductModal();
                    showToastNotification('تمت إضافة المنتج بنجاح وتعيين المخزون', 'success');
                    loadCatalogProducts(currentActiveCategory);
                } else {
                    handleApiError(res, json, 'فشلت إضافة المنتج');
                }
            } catch (err) {
                showToastNotification('تعذر حفظ بيانات المنتج، يرجى المحاولة لاحقاً', 'error');
            }
        }

        // ========================================================
        // الإشعارات
        // ========================================================
        async function loadNotificationsList() {
            if (!sessionUser.token) return;

            try {
                const res = await fetch(`${API_PREFIX}/notifications`, {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${sessionUser.token}`
                    }
                });

                const json = await res.json();
                if (!res.ok) {
                    handleApiError(res, json, 'تعذر تحميل الإشعارات');
                    return;
                }

                const list = json.data || [];
                const container = document.getElementById('notificationsContainer');
                container.innerHTML = '';

                if (list.length === 0) {
                    container.innerHTML = `
                        <div class="py-12 text-center text-slate-400 text-xs">
                            <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            لا توجد إشعارات مسجلة في حسابك حالياً.
                        </div>
                    `;
                } else {
                    list.forEach(n => {
                        const isRead = !!n.read;
                        let title = 'إشعار جديد';
                        let desc = n.message || 'لا توجد تفاصيل إضافية.';
                        let iconSvg = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';

                        if (n.order_id) {
                            if (n.status === 'confirmed') {
                                title = `تم تأكيد طلب الشراء #${n.order_id}`;
                                desc = `تم تأكيد طلبك بنجاح وهو الآن قيد التجهيز.`;
                                iconSvg = '<svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
                            } else if (n.status === 'shipped') {
                                title = `تم شحن الطلب #${n.order_id}`;
                                desc = `تم تسليم طلبك لشركة الشحن وهو في طريقه إليك.`;
                                iconSvg = '<svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>';
                            } else if (n.status === 'delivered') {
                                title = `تم تسليم الطلب #${n.order_id}`;
                                desc = `تم تسليم الطلب بنجاح. نتمنى لك تجربة ممتعة!`;
                                iconSvg = '<svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                            } else if (n.status === 'cancelled') {
                                title = `تم إلغاء الطلب #${n.order_id}`;
                                desc = `تم إلغاء الطلب واسترجاع الكميات المخصصة له.`;
                                iconSvg = '<svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
                            } else if (n.status === 'failed') {
                                title = `تعذر معالجة الطلب #${n.order_id}`;
                                desc = `حدث تعثر في استكمال الطلب، وتم إلغاء حجز الكميات تلقائياً.`;
                                iconSvg = '<svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                            } else if (n.status === 'rejected') {
                                title = `تم رفض الطلب #${n.order_id}`;
                                desc = `تم رفض الطلب نظراً لعدم توفر الكميات المطلوبة في المخزون.`;
                                iconSvg = '<svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                            } else {
                                title = `تحديث بخصوص الطلب #${n.order_id}`;
                                desc = n.message || `حالة الطلب الحالية: ${n.status}`;
                            }
                        }

                        const el = document.createElement('div');
                        el.className = `p-4 rounded-xl border transition flex items-start justify-between gap-3 ${
                            isRead ? 'bg-slate-50/70 border-slate-200' : 'bg-white border-blue-200 shadow-xs ring-1 ring-blue-100'
                        }`;
                        el.innerHTML = `
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5 ${
                                    isRead ? 'bg-slate-200 text-slate-500' : 'bg-blue-50 text-blue-600'
                                }">
                                    ${iconSvg}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs ${isRead ? 'text-slate-700' : 'text-slate-900'}">${title}</span>
                                        ${!isRead ? '<span class="w-2 h-2 rounded-full bg-blue-600"></span>' : ''}
                                    </div>
                                    <p class="text-xs text-slate-600 mt-1 leading-relaxed">${desc}</p>
                                    ${n.order_id ? `
                                        <button onclick="openOrderDetails(${n.order_id})" class="text-[11px] font-bold text-blue-600 hover:text-blue-800 hover:underline mt-1.5 inline-flex items-center gap-1">
                                            <span>عرض تفاصيل الطلب #${n.order_id}</span>
                                            <span class="text-xs">←</span>
                                        </button>
                                    ` : ''}
                                    <span class="text-[10px] text-slate-400 mt-1.5 block font-mono font-normal">
                                        ${new Date(n.created_at).toLocaleString('ar-SY', { dateStyle: 'medium', timeStyle: 'short' })}
                                    </span>
                                </div>
                            </div>
                            <div class="shrink-0 pt-0.5">
                                ${!isRead ? `
                                    <button onclick="markNotificationRead('${n.id}')" class="px-2.5 py-1 text-[11px] font-bold text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 rounded-lg border border-blue-200 transition">
                                        تحديد كمقروء
                                    </button>
                                ` : `
                                    <span class="text-[10px] text-slate-400 font-semibold">مقروء</span>
                                `}
                            </div>
                        `;
                        container.appendChild(el);
                    });
                }

                const unreadCount = json.meta?.unread ?? list.filter(n => !n.read).length;
                const badge = document.getElementById('notifBadge');
                if (unreadCount > 0) {
                    badge.innerText = unreadCount;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function markNotificationRead(id) {
            try {
                const res = await fetch(`${API_PREFIX}/notifications/${id}/read`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${sessionUser.token}`
                    }
                });
                const json = await res.json();
                if (res.ok) {
                    loadNotificationsList();
                } else {
                    handleApiError(res, json, 'تعذر تحديث حالة الإشعار');
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function markAllNotificationsRead() {
            try {
                const res = await fetch(`${API_PREFIX}/notifications`, {
                    headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${sessionUser.token}` }
                });
                const json = await res.json();
                const unreadItems = (json.data || []).filter(n => !n.read);
                await Promise.all(unreadItems.map(n => 
                    fetch(`${API_PREFIX}/notifications/${n.id}/read`, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${sessionUser.token}` }
                    })
                ));
                showToastNotification('تم تحديد كافة الإشعارات كمقروءة', 'success');
                loadNotificationsList();
            } catch (err) {
                console.error(err);
            }
        }

        // ========================================================
        // الملف الشخصي والتنبيهات
        // ========================================================
        function openProfileModal() {
            document.getElementById('modalUserProfile').classList.add('active');
        }

        function closeProfileModal() {
            document.getElementById('modalUserProfile').classList.remove('active');
        }

        function showToastNotification(msg, type = 'info') {
            const container = document.getElementById('toastContainer');
            const alert = document.getElementById('toastAlert');
            const msgEl = document.getElementById('toastMessage');
            const iconEl = document.getElementById('toastIcon');

            msgEl.innerText = msg;

            if (type === 'success') {
                alert.className = 'pointer-events-auto p-3.5 rounded-xl border shadow-lg flex items-center justify-between text-xs bg-emerald-50 border-emerald-200 text-emerald-950';
                iconEl.className = 'w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-xs';
                iconEl.innerText = '✓';
            } else if (type === 'error') {
                alert.className = 'pointer-events-auto p-3.5 rounded-xl border shadow-lg flex items-center justify-between text-xs bg-rose-50 border-rose-200 text-rose-950';
                iconEl.className = 'w-5 h-5 rounded-full bg-rose-500 text-white flex items-center justify-center font-bold text-xs';
                iconEl.innerText = '!';
            } else {
                alert.className = 'pointer-events-auto p-3.5 rounded-xl border shadow-lg flex items-center justify-between text-xs bg-blue-50 border-blue-200 text-blue-950';
                iconEl.className = 'w-5 h-5 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-xs';
                iconEl.innerText = 'i';
            }

            container.classList.remove('opacity-0', '-translate-y-4');
            container.classList.add('opacity-100', 'translate-y-0');

            setTimeout(() => {
                hideToast();
            }, 3500);
        }

        function hideToast() {
            const container = document.getElementById('toastContainer');
            container.classList.remove('opacity-100', 'translate-y-0');
            container.classList.add('opacity-0', '-translate-y-4');
        }
    </script>
</body>
</html>
