<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Portal | Tahlil Sonuç Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .portal-card { max-width: 480px; }
        .field-hint { font-size: .85rem; }
    </style>
</head>
<body class="bg-body-tertiary">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/">Lab Portal</a>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-light btn-sm" id="darkModeBtn">🌙 Koyu Mod</button>

            <!-- Giriş yapılmamışken görünenler -->
            <button class="btn btn-light btn-sm fw-bold auth-hidden" id="showLoginBtn">Personel Girişi</button>

            <!-- Giriş yapılmışken görünenler -->
            <span class="text-white-50 small auth-only d-none" id="currentUserLabel"></span>
            <button class="btn btn-warning btn-sm fw-bold auth-only admin-only d-none" id="viewLogsBtn">Sistem Logları</button>
            <button class="btn btn-outline-light btn-sm auth-only d-none" id="logoutBtn">Oturumu Kapat</button>
        </div>
    </div>
</nav>

<div class="container my-4">

    {{-- ---------- 1. BÖLÜM: HASTA SORGULAMA (herkese açık) ---------- --}}
    <section id="publicSection">
        <div class="row justify-content-center">
            <div class="col-12 portal-card">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white text-center">
                        <h5 class="mb-0">Tahlil Sonucu Sorgulama</h5>
                    </div>
                    <div class="card-body">
                        <form id="searchForm" novalidate>
                            <div class="mb-3">
                                <label for="barcodeInput" class="form-label">Barkod Numaranız</label>
                                <input type="text" class="form-control" id="barcodeInput"
                                       maxlength="50" autocomplete="off" placeholder="Örn: BARKOD-2026-001" required>
                            </div>
                            <div class="mb-3">
                                <label for="lastFourInput" class="form-label">T.C. Kimlik No (Son 4 Hane)</label>
                                <input type="text" inputmode="numeric" class="form-control numeric-only"
                                       id="lastFourInput" maxlength="4" autocomplete="off" placeholder="Örn: 8950" required>
                                <div class="form-text field-hint">
                                    Sonucun size ait olduğunu doğrulamak için kimlik numaranızın son 4 hanesi gerekir.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold" id="searchBtn">Sonucumu Bul</button>
                        </form>

                        <div id="searchError" class="alert alert-danger mt-3 d-none" role="alert"></div>

                        <div id="resultArea" class="mt-4 d-none">
                            <div class="alert alert-success mb-0">
                                <h6 id="patientName" class="alert-heading fw-bold"></h6>
                                <span id="testName" class="badge bg-secondary mb-2"></span>
                                <p id="testDetails" class="mb-0" style="white-space: pre-wrap;"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ---------- 2. BÖLÜM: LABORANT GİRİŞİ ---------- --}}
    <section id="loginSection" class="d-none">
        <div class="row justify-content-center">
            <div class="col-12 portal-card">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white text-center">
                        <h5 class="mb-0">Laborant Girişi</h5>
                    </div>
                    <div class="card-body">
                        <form id="loginForm" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-Posta Adresi</label>
                                <input type="email" class="form-control" id="email" autocomplete="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Şifre</label>
                                <input type="password" class="form-control" id="password" autocomplete="current-password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold" id="loginBtn">Giriş Yap</button>
                        </form>

                        <div id="loginError" class="alert alert-danger mt-3 d-none text-center" role="alert"></div>

                        <button class="btn btn-link w-100 mt-2" id="backToSearchBtn">← Sonuç sorgulama ekranına dön</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ---------- 3. BÖLÜM: YÖNETİM PANELİ ---------- --}}
    <section id="adminSection" class="d-none">
        <div class="row mb-3 g-2">
            <div class="col-6 col-md-3">
                <div class="card text-white bg-primary shadow-sm h-100">
                    <div class="card-body p-2 text-center">
                        <small class="text-uppercase text-white-50 d-block mb-1">Toplam Hasta</small>
                        <h4 class="mb-0 fw-bold" id="statPatients">0</h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-white bg-success shadow-sm h-100">
                    <div class="card-body p-2 text-center">
                        <small class="text-uppercase text-white-50 d-block mb-1">Toplam Tahlil</small>
                        <h4 class="mb-0 fw-bold" id="statTotalTests">0</h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-dark bg-warning shadow-sm h-100">
                    <div class="card-body p-2 text-center">
                        <small class="text-uppercase d-block mb-1">Bugünkü Testler</small>
                        <h4 class="mb-0 fw-bold" id="statTodayTests">0</h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-white bg-danger shadow-sm h-100">
                    <div class="card-body p-2 text-center">
                        <small class="text-uppercase text-white-50 d-block mb-1">Silinen Kayıtlar</small>
                        <h4 class="mb-0 fw-bold" id="statTrashed">0</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h4 class="mb-0">Sistemdeki Tahlil Sonuçları</h4>
            <div>
                <button class="btn btn-secondary fw-bold me-2 admin-only d-none" id="trashBtn">🗑️ Çöp Kutusu</button>
                <button class="btn btn-success fw-bold" id="addNewBtn">+ Yeni Kayıt Ekle</button>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control border-primary"
                       placeholder="🔍 Barkod No veya Hasta Adı Ara..." autocomplete="off">
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-striped mb-0 text-center align-middle">
                    <thead class="table-primary">
                        <tr>
                            <th>ID</th>
                            <th>Barkod No</th>
                            <th>Hasta Adı</th>
                            <th>Tahlil Adı</th>
                            <th>Kayıt Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody id="resultsTableBody">
                        <tr><td colspan="6" class="text-muted p-4">Veriler yükleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-center mt-3" id="paginationControls"></div>
    </section>
</div>

{{-- ---------- MODALLAR ---------- --}}

<div class="modal fade" id="addRecordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Yeni Tahlil Sonucu Ekle</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <form id="addRecordForm" novalidate>
            <div class="mb-3">
                <label class="form-label" for="addIdentity">T.C. Kimlik No</label>
                <input type="text" inputmode="numeric" class="form-control numeric-only"
                       id="addIdentity" maxlength="11" autocomplete="off" required>
                <div class="invalid-feedback" id="addIdentityFeedback"></div>
                <div class="form-text field-hint">11 hane, yalnızca rakam. Numara doğrulama algoritmasından geçmelidir.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="addFullName">Hasta Adı Soyadı</label>
                <input type="text" class="form-control" id="addFullName" maxlength="255" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="addBarcode">Barkod Numarası</label>
                <input type="text" class="form-control" id="addBarcode" maxlength="50" autocomplete="off" required>
                <div class="form-text field-hint">Yalnızca harf, rakam ve tire (-).</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="addTestName">Tahlil Adı (Örn: Kan Tahlili)</label>
                <input type="text" class="form-control" id="addTestName" maxlength="255" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="addDetails">Sonuç Detayları</label>
                <textarea class="form-control" id="addDetails" rows="3" maxlength="5000" required></textarea>
            </div>
            <div id="addRecordError" class="alert alert-danger d-none"></div>
            <button type="submit" class="btn btn-success w-100 fw-bold">Kaydet</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editRecordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Tahlil Sonucunu Düzenle</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <form id="editRecordForm" novalidate>
            <input type="hidden" id="editRecordId">
            <div class="mb-3">
                <label class="form-label text-muted" for="editFullName">Hasta Adı (Değiştirilemez)</label>
                <input type="text" class="form-control" id="editFullName" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted" for="editBarcode">Barkod Numarası (Değiştirilemez)</label>
                <input type="text" class="form-control" id="editBarcode" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold" for="editTestName">Tahlil Adı</label>
                <input type="text" class="form-control" id="editTestName" maxlength="255" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold" for="editDetails">Sonuç Detayları</label>
                <textarea class="form-control" id="editDetails" rows="4" maxlength="5000" required></textarea>
            </div>
            <div id="editRecordError" class="alert alert-danger d-none"></div>
            <button type="submit" class="btn btn-primary w-100 fw-bold">Güncelle</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="systemLogsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title fw-bold">Sistem İşlem Logları</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body p-0 table-responsive">
        <table class="table table-striped table-hover mb-0 text-center" style="font-size: .9rem;">
            <thead class="table-dark">
                <tr><th>Tarih / Saat</th><th>Kullanıcı</th><th>İşlem</th><th>Detay</th></tr>
            </thead>
            <tbody id="logsTableBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="trashModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title">Çöp Kutusu (Silinen Kayıtlar)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body p-0 table-responsive">
        <table class="table table-hover table-striped mb-0 text-center align-middle">
            <thead class="table-dark">
                <tr><th>Barkod No</th><th>Hasta Adı</th><th>Tahlil Adı</th><th>Silinme Tarihi</th><th>İşlem</th></tr>
            </thead>
            <tbody id="trashTableBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function () {

    /* ---------------------------------------------------------------
     * Yardımcılar
     * --------------------------------------------------------------- */

    // Veritabanından gelen metinler tabloya basılmadan önce kaçışlanır.
    // Aksi halde hasta adı gibi alanlara yazılan HTML paneli açan herkeste çalışır.
    function esc(value) {
        return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
    }

    function formatDate(value) {
        return value ? new Date(value).toLocaleDateString('tr-TR') : '-';
    }

    function formatDateTime(value) {
        return value ? new Date(value).toLocaleString('tr-TR') : '-';
    }

    function showAlert($box, message) {
        $box.removeClass('d-none').text(message);
    }

    function hideAlert($box) {
        $box.addClass('d-none').text('');
    }

    // Sunucudan dönen doğrulama hatalarını tek bir metne çevirir
    function errorMessage(xhr, fallback) {
        var json = xhr.responseJSON;
        if (json && json.errors) {
            return Object.keys(json.errors).map(function (key) {
                return json.errors[key][0];
            }).join('\n');
        }
        if (json && json.message) {
            return json.message;
        }
        return fallback;
    }

    // Sadece rakam kabul eden alanlar
    $(document).on('input', '.numeric-only', function () {
        this.value = this.value.replace(/\D/g, '');
    });

    /* ---------------------------------------------------------------
     * T.C. Kimlik Numarası doğrulaması (sunucudaki kuralın aynısı)
     * --------------------------------------------------------------- */
    function validateIdentityNumber(value) {
        if (!/^\d+$/.test(value)) {
            return 'T.C. Kimlik Numarası yalnızca rakamlardan oluşmalıdır.';
        }
        if (value.length !== 11) {
            return 'T.C. Kimlik Numarası 11 haneli olmalıdır.';
        }

        var d = value.split('').map(Number);

        if (d[0] === 0) {
            return 'T.C. Kimlik Numarası 0 ile başlayamaz.';
        }

        var oddSum = d[0] + d[2] + d[4] + d[6] + d[8];
        var evenSum = d[1] + d[3] + d[5] + d[7];

        // 10. hane kontrolü. Fark negatif olabileceği için sonucu normalize ediyoruz.
        if (((((oddSum * 7) - evenSum) % 10) + 10) % 10 !== d[9]) {
            return 'Geçersiz bir T.C. Kimlik Numarası girdiniz.';
        }

        // 11. hane kontrolü. Bu kural gereği geçerli numaralar daima çift rakamla biter.
        var firstTenSum = d.slice(0, 10).reduce(function (a, b) { return a + b; }, 0);
        if (firstTenSum % 10 !== d[10]) {
            return 'Geçersiz bir T.C. Kimlik Numarası girdiniz.';
        }

        return null;
    }

    // Kullanıcı yazarken anlık geri bildirim
    $('#addIdentity').on('input blur', function () {
        var value = $(this).val();

        if (value === '') {
            $(this).removeClass('is-invalid is-valid');
            return;
        }

        var error = validateIdentityNumber(value);

        // Yazma sırasında henüz tamamlanmamış numarayı hatalı göstermeyelim
        if (error && value.length < 11 && $(this).is(':focus')) {
            $(this).removeClass('is-invalid is-valid');
            return;
        }

        $('#addIdentityFeedback').text(error || '');
        $(this).toggleClass('is-invalid', !!error).toggleClass('is-valid', !error);
    });

    /* ---------------------------------------------------------------
     * Oturum durumu ve bölümler arası geçiş
     * --------------------------------------------------------------- */
    var token = localStorage.getItem('lab_token');
    var currentUser = null;
    var currentPage = 1;
    var currentSearch = '';
    var searchTimer = null;

    function authHeaders() {
        return { 'Authorization': 'Bearer ' + token };
    }

    function showSection(name) {
        $('#publicSection').toggleClass('d-none', name !== 'public');
        $('#loginSection').toggleClass('d-none', name !== 'login');
        $('#adminSection').toggleClass('d-none', name !== 'admin');
    }

    function applyAuthState() {
        var loggedIn = !!currentUser;
        var isAdmin = loggedIn && currentUser.role === 'admin';

        $('.auth-only').toggleClass('d-none', !loggedIn);
        $('.auth-hidden').toggleClass('d-none', loggedIn);
        // Yönetici olmayan kullanıcı silme, çöp kutusu ve log ekranlarını görmez
        $('.admin-only').toggleClass('d-none', !isAdmin);

        if (loggedIn) {
            $('#currentUserLabel').text(currentUser.name + (isAdmin ? ' (Yönetici)' : ' (Laborant)'));
        }
    }

    function enterAdmin(user) {
        currentUser = user;
        applyAuthState();
        showSection('admin');
        fetchResults();
        fetchStatistics();
    }

    function clearSession() {
        localStorage.removeItem('lab_token');
        token = null;
        currentUser = null;
        applyAuthState();
        showSection('public');
    }

    // Sayfa açılışında hafızadaki token hâlâ geçerli mi diye sunucuya soruyoruz
    if (token) {
        $.ajax({
            url: '/api/user',
            type: 'GET',
            headers: authHeaders(),
            success: function (user) { enterAdmin(user); },
            error: function () { clearSession(); }
        });
    } else {
        applyAuthState();
        showSection('public');
    }

    $('#showLoginBtn').click(function () {
        hideAlert($('#loginError'));
        showSection('login');
    });

    $('#backToSearchBtn').click(function () {
        showSection('public');
    });

    /* ---------------------------------------------------------------
     * 1. Hasta sorgulama
     * --------------------------------------------------------------- */
    $('#searchForm').submit(function (e) {
        e.preventDefault();

        var barcode = $.trim($('#barcodeInput').val());
        var lastFour = $('#lastFourInput').val();

        hideAlert($('#searchError'));

        if (barcode === '') {
            showAlert($('#searchError'), 'Lütfen barkod numaranızı girin.');
            return;
        }
        if (!/^\d{4}$/.test(lastFour)) {
            showAlert($('#searchError'), 'T.C. Kimlik Numaranızın son 4 hanesini girin.');
            return;
        }

        var $btn = $('#searchBtn');
        $btn.prop('disabled', true).text('Aranıyor...');

        $.ajax({
            url: '/api/sonuc/' + encodeURIComponent(barcode),
            type: 'POST',
            data: { identity_last_four: lastFour },
            success: function (response) {
                // .text() kullanıldığı için gelen veri HTML olarak yorumlanmaz
                $('#patientName').text('Hasta: ' + response.patient_name);
                $('#testName').text(response.test_name);
                $('#testDetails').text(response.result_details);
                $('#resultArea').removeClass('d-none');
            },
            error: function (xhr) {
                var message = xhr.status === 429
                    ? 'Çok fazla deneme yaptınız. Lütfen bir dakika sonra tekrar deneyin.'
                    : errorMessage(xhr, 'Girilen bilgilere ait bir sonuç bulunamadı.');
                showAlert($('#searchError'), message);
                $('#resultArea').addClass('d-none');
            },
            complete: function () {
                $btn.prop('disabled', false).text('Sonucumu Bul');
            }
        });
    });

    /* ---------------------------------------------------------------
     * 2. Laborant girişi
     * --------------------------------------------------------------- */
    $('#loginForm').submit(function (e) {
        e.preventDefault();
        hideAlert($('#loginError'));

        var $btn = $('#loginBtn');
        $btn.prop('disabled', true).text('Giriş yapılıyor...');

        $.ajax({
            url: '/api/login',
            type: 'POST',
            data: {
                email: $('#email').val(),
                password: $('#password').val()
            },
            success: function (response) {
                localStorage.setItem('lab_token', response.token);
                token = response.token;
                $('#loginForm')[0].reset();
                // Sayfa yenilenmeden doğrudan panele geçiyoruz
                enterAdmin(response.user);
            },
            error: function (xhr) {
                var message = xhr.status === 429
                    ? 'Çok fazla hatalı deneme. Lütfen bir dakika sonra tekrar deneyin.'
                    : errorMessage(xhr, 'Giriş sırasında bir hata oluştu.');
                showAlert($('#loginError'), message);
            },
            complete: function () {
                $btn.prop('disabled', false).text('Giriş Yap');
            }
        });
    });

    $('#logoutBtn').click(function () {
        // Token'ı sunucuda da iptal ettiriyoruz
        $.ajax({
            url: '/api/logout',
            type: 'POST',
            headers: authHeaders(),
            complete: function () { clearSession(); }
        });
    });

    /* ---------------------------------------------------------------
     * 3. Yönetim paneli
     * --------------------------------------------------------------- */
    function fetchResults(page, search) {
        page = page || 1;
        search = search || '';

        $.ajax({
            url: '/api/sonuclar',
            type: 'GET',
            data: { page: page, search: search },
            headers: authHeaders(),
            success: function (response) {
                var tbody = $('#resultsTableBody').empty();
                var results = response.data || [];

                if (results.length === 0) {
                    tbody.append('<tr><td colspan="6" class="text-muted p-4">Kayıt bulunamadı.</td></tr>');
                } else {
                    var isAdmin = currentUser && currentUser.role === 'admin';

                    results.forEach(function (item) {
                        var patientName = item.patient ? item.patient.full_name : '-';
                        var deleteBtn = isAdmin
                            ? '<button class="btn btn-sm btn-outline-danger delete-btn" data-id="' + esc(item.id) + '">Sil</button>'
                            : '';

                        tbody.append(
                            '<tr>' +
                                '<td>' + esc(item.id) + '</td>' +
                                '<td><span class="badge bg-secondary">' + esc(item.barcode_number) + '</span></td>' +
                                '<td class="fw-bold">' + esc(patientName) + '</td>' +
                                '<td>' + esc(item.test_name) + '</td>' +
                                '<td>' + esc(formatDate(item.created_at)) + '</td>' +
                                '<td>' +
                                    '<a href="/yazdir/' + encodeURIComponent(item.id) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Yazdır</a> ' +
                                    '<button class="btn btn-sm btn-outline-primary edit-btn" data-id="' + esc(item.id) + '">Düzenle</button> ' +
                                    deleteBtn +
                                '</td>' +
                            '</tr>'
                        );
                    });
                }

                renderPagination(response);
            },
            error: function (xhr) {
                if (xhr.status === 401) {
                    clearSession();
                    return;
                }
                $('#resultsTableBody').html('<tr><td colspan="6" class="text-danger p-4">Veriler çekilirken bir hata oluştu.</td></tr>');
            }
        });
    }

    function renderPagination(response) {
        var pagination = $('#paginationControls').empty();

        if (!response.last_page || response.last_page <= 1) {
            return;
        }

        var html = '<ul class="pagination pagination-sm mb-0">';

        html += '<li class="page-item ' + (response.current_page === 1 ? 'disabled' : '') + '">' +
                '<a class="page-link page-action" href="#" data-page="' + (response.current_page - 1) + '">Önceki</a></li>';

        for (var i = 1; i <= response.last_page; i++) {
            html += '<li class="page-item ' + (response.current_page === i ? 'active' : '') + '">' +
                    '<a class="page-link page-action" href="#" data-page="' + i + '">' + i + '</a></li>';
        }

        html += '<li class="page-item ' + (response.current_page === response.last_page ? 'disabled' : '') + '">' +
                '<a class="page-link page-action" href="#" data-page="' + (response.current_page + 1) + '">Sonraki</a></li>';

        pagination.append(html + '</ul>');
    }

    function fetchStatistics() {
        $.ajax({
            url: '/api/istatistikler',
            type: 'GET',
            headers: authHeaders(),
            success: function (response) {
                $('#statPatients').text(response.total_patients);
                $('#statTotalTests').text(response.total_tests);
                $('#statTodayTests').text(response.today_tests);
                $('#statTrashed').text(response.trashed_tests);
            }
        });
    }

    // Arama: her tuş vuruşunda istek atmamak için kısa bir gecikme
    $('#searchInput').on('keyup', function () {
        var value = $(this).val();

        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            currentSearch = value;
            currentPage = 1;
            fetchResults(currentPage, currentSearch);
        }, 300);
    });

    $(document).on('click', '.page-action', function (e) {
        e.preventDefault();
        var page = $(this).data('page');
        if (page) {
            currentPage = page;
            fetchResults(currentPage, currentSearch);
        }
    });

    /* ---------- Yeni kayıt ---------- */
    $('#addNewBtn').click(function () {
        hideAlert($('#addRecordError'));
        $('#addIdentity').removeClass('is-invalid is-valid');
        new bootstrap.Modal(document.getElementById('addRecordModal')).show();
    });

    $('#addRecordForm').submit(function (e) {
        e.preventDefault();
        hideAlert($('#addRecordError'));

        var identity = $('#addIdentity').val();
        var identityError = validateIdentityNumber(identity);

        // Sunucu da aynı kontrolü yapıyor; buradaki amaç anında geri bildirim
        if (identityError) {
            $('#addIdentity').addClass('is-invalid');
            $('#addIdentityFeedback').text(identityError);
            showAlert($('#addRecordError'), identityError);
            return;
        }

        $.ajax({
            url: '/api/sonuclar',
            type: 'POST',
            headers: authHeaders(),
            data: {
                identity_number: identity,
                full_name: $('#addFullName').val(),
                barcode_number: $('#addBarcode').val(),
                test_name: $('#addTestName').val(),
                result_details: $('#addDetails').val()
            },
            success: function (response) {
                bootstrap.Modal.getInstance(document.getElementById('addRecordModal')).hide();
                $('#addRecordForm')[0].reset();
                $('#addIdentity').removeClass('is-invalid is-valid');
                fetchResults(currentPage, currentSearch);
                fetchStatistics();
            },
            error: function (xhr) {
                showAlert($('#addRecordError'), errorMessage(xhr, 'Kayıt eklenirken bir hata oluştu.'));
            }
        });
    });

    /* ---------- Düzenleme ---------- */
    $(document).on('click', '.edit-btn', function () {
        var id = $(this).data('id');
        hideAlert($('#editRecordError'));

        $.ajax({
            url: '/api/sonuclar/' + encodeURIComponent(id),
            type: 'GET',
            headers: authHeaders(),
            success: function (response) {
                $('#editRecordId').val(response.id);
                $('#editFullName').val(response.patient ? response.patient.full_name : '');
                $('#editBarcode').val(response.barcode_number);
                $('#editTestName').val(response.test_name);
                $('#editDetails').val(response.result_details);
                new bootstrap.Modal(document.getElementById('editRecordModal')).show();
            },
            error: function () {
                alert('Kayıt bilgileri getirilemedi!');
            }
        });
    });

    $('#editRecordForm').submit(function (e) {
        e.preventDefault();
        hideAlert($('#editRecordError'));

        $.ajax({
            url: '/api/sonuclar/' + encodeURIComponent($('#editRecordId').val()),
            type: 'PUT',
            headers: authHeaders(),
            data: {
                test_name: $('#editTestName').val(),
                result_details: $('#editDetails').val()
            },
            success: function () {
                bootstrap.Modal.getInstance(document.getElementById('editRecordModal')).hide();
                fetchResults(currentPage, currentSearch);
            },
            error: function (xhr) {
                showAlert($('#editRecordError'), errorMessage(xhr, 'Güncelleme sırasında bir hata oluştu.'));
            }
        });
    });

    /* ---------- Silme (yalnızca yönetici) ---------- */
    $(document).on('click', '.delete-btn', function () {
        var id = $(this).data('id');

        if (!confirm('Bu tahlil sonucunu silmek istediğinize emin misiniz? (Silinen kayıtlar Çöp Kutusu\'na taşınır.)')) {
            return;
        }

        $.ajax({
            url: '/api/sonuclar/' + encodeURIComponent(id),
            type: 'DELETE',
            headers: authHeaders(),
            success: function () {
                fetchResults(currentPage, currentSearch);
                fetchStatistics();
            },
            error: function (xhr) {
                alert(errorMessage(xhr, 'Silme işlemi sırasında bir hata oluştu.'));
            }
        });
    });

    /* ---------- Sistem logları (yalnızca yönetici) ---------- */
    $('#viewLogsBtn').click(function () {
        $.ajax({
            url: '/api/loglar',
            type: 'GET',
            headers: authHeaders(),
            success: function (response) {
                var tbody = $('#logsTableBody').empty();

                if (response.length === 0) {
                    tbody.append('<tr><td colspan="4" class="text-muted p-3">Henüz sistemde kaydedilmiş bir işlem yok.</td></tr>');
                } else {
                    response.forEach(function (log) {
                        var badgeClass = 'bg-secondary';
                        if (log.action === 'Ekleme') badgeClass = 'bg-success';
                        else if (log.action === 'Güncelleme') badgeClass = 'bg-primary';
                        else if (log.action === 'Silme') badgeClass = 'bg-danger';

                        tbody.append(
                            '<tr>' +
                                '<td class="text-muted">' + esc(formatDateTime(log.created_at)) + '</td>' +
                                '<td class="fw-bold">' + esc(log.user ? log.user.name : 'Bilinmiyor') + '</td>' +
                                '<td><span class="badge ' + badgeClass + '">' + esc(log.action) + '</span></td>' +
                                '<td class="text-start">' + esc(log.description) + '</td>' +
                            '</tr>'
                        );
                    });
                }

                new bootstrap.Modal(document.getElementById('systemLogsModal')).show();
            },
            error: function (xhr) {
                alert(errorMessage(xhr, 'Loglar çekilirken bir hata oluştu.'));
            }
        });
    });

    /* ---------- Çöp kutusu ---------- */
    $('#trashBtn').click(function () {
        $.ajax({
            url: '/api/sonuclar/cop-kutusu',
            type: 'GET',
            headers: authHeaders(),
            success: function (response) {
                var tbody = $('#trashTableBody').empty();

                if (response.length === 0) {
                    tbody.append('<tr><td colspan="5" class="text-muted p-4">Çöp kutusu boş.</td></tr>');
                } else {
                    response.forEach(function (item) {
                        var patientName = item.patient ? item.patient.full_name : '-';

                        tbody.append(
                            '<tr>' +
                                '<td><span class="badge bg-secondary">' + esc(item.barcode_number) + '</span></td>' +
                                '<td class="fw-bold">' + esc(patientName) + '</td>' +
                                '<td>' + esc(item.test_name) + '</td>' +
                                '<td class="text-danger">' + esc(formatDateTime(item.deleted_at)) + '</td>' +
                                '<td><button class="btn btn-sm btn-success restore-btn" data-id="' + esc(item.id) + '">Geri Yükle</button></td>' +
                            '</tr>'
                        );
                    });
                }

                new bootstrap.Modal(document.getElementById('trashModal')).show();
            },
            error: function (xhr) {
                alert(errorMessage(xhr, 'Çöp kutusu verileri getirilemedi.'));
            }
        });
    });

    $(document).on('click', '.restore-btn', function () {
        $.ajax({
            url: '/api/sonuclar/' + encodeURIComponent($(this).data('id')) + '/geri-yukle',
            type: 'PUT',
            headers: authHeaders(),
            success: function () {
                bootstrap.Modal.getInstance(document.getElementById('trashModal')).hide();
                fetchResults(currentPage, currentSearch);
                fetchStatistics();
            },
            error: function (xhr) {
                alert(errorMessage(xhr, 'Geri yükleme işlemi başarısız oldu.'));
            }
        });
    });

    /* ---------------------------------------------------------------
     * Koyu mod
     * --------------------------------------------------------------- */
    var $darkModeBtn = $('#darkModeBtn');

    if (localStorage.getItem('lab_theme') === 'dark') {
        $('html').attr('data-bs-theme', 'dark');
        $darkModeBtn.html('☀️ Açık Mod');
    }

    $darkModeBtn.click(function () {
        if ($('html').attr('data-bs-theme') === 'dark') {
            $('html').removeAttr('data-bs-theme');
            localStorage.setItem('lab_theme', 'light');
            $(this).html('🌙 Koyu Mod');
        } else {
            $('html').attr('data-bs-theme', 'dark');
            localStorage.setItem('lab_theme', 'dark');
            $(this).html('☀️ Açık Mod');
        }
    });
});
</script>
</body>
</html>
