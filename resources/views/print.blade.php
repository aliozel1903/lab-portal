<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.demo_mode') ? 'TEST BELGESİ - Tahlil Sonucu' : 'Resmi Tahlil Sonuç Belgesi' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Sadece yazıcıda (veya PDF'te) geçerli olan CSS kuralları */
        @media print {
            .no-print { display: none !important; } /* Yazdır butonunu kağıtta gizle */
            body { background-color: white !important; }
            .container { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
        }
        .header { border-bottom: 3px solid #333; padding-bottom: 15px; margin-bottom: 30px; }

        /* Demo modunda her sayfanın ortasına çapraz "TEST BELGESİ" filigranı */
        .test-watermark {
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 6rem; font-weight: 800; letter-spacing: .5rem;
            color: rgba(220, 53, 69, .12); white-space: nowrap;
            pointer-events: none; z-index: 0;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
    </style>
</head>
<body class="bg-light pt-4">

@if (config('app.demo_mode'))
    <div class="test-watermark" aria-hidden="true">TEST BELGESİ</div>
@endif

<div class="container bg-white p-5 shadow-sm" style="max-width: 800px;">
    <!-- Kurum Başlığı -->
    <div class="text-center header">
        <h2 class="fw-bold">ULUDAĞ BİLİŞİM LABORATUVARLARI</h2>
        @if (config('app.demo_mode'))
            <h5 class="text-danger fw-bold mb-1">TEST BELGESİ</h5>
            <p class="text-muted small mb-0">Demo sistemi çıktısıdır, resmî geçerliliği yoktur. Tüm veriler kurgusaldır.</p>
        @else
            <h5 class="text-muted mb-0">Resmi Tahlil Sonuç Belgesi</h5>
        @endif
    </div>
    
    <!-- Hasta ve Barkod Bilgileri -->
    <div class="row mb-5">
        <div class="col-6">
            <p class="mb-1"><strong>Hasta Adı:</strong> <span id="pName">Yükleniyor...</span></p>
            <p class="mb-1"><strong>TC Kimlik No:</strong> <span id="pTc">Yükleniyor...</span></p>
        </div>
        <div class="col-6 text-end">
            <p class="mb-1"><strong>Barkod Numarası:</strong> <span id="pBarcode">Yükleniyor...</span></p>
            <p class="mb-1"><strong>İşlem Tarihi:</strong> <span id="pDate">Yükleniyor...</span></p>
        </div>
    </div>

    <!-- Tahlil Sonucu Bölümü -->
    <div class="mb-5" style="min-height: 200px;">
        <h4 class="border-bottom pb-2 mb-4 text-primary">Tahlil Adı: <span id="pTestName"></span></h4>
        <p id="pDetails" style="font-size: 1.1rem; white-space: pre-wrap;"></p>
    </div>

    <!-- İmza Alanı -->
    <div class="row mt-5 pt-5 text-center">
        <div class="col-7"></div>
        <div class="col-5">
            <p class="mb-0 fw-bold">Onaylayan Uzman / Laborant</p>
            @if (config('app.demo_mode'))
                <p class="text-muted" style="font-size: 0.9rem;">(Demo belgesi, imzasızdır)</p>
            @else
                <p class="text-muted" style="font-size: 0.9rem;">(Elektronik İmzalıdır)</p>
            @endif
        </div>
    </div>
    
    <!-- Ekranda görünen ama yazıcıda gizlenen Buton -->
    <div class="text-center mt-5 no-print border-top pt-4">
        <button class="btn btn-dark btn-lg px-5" onclick="window.print()">🖨️ PDF Kaydet / Yazdır</button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $(document).ready(function() {
        // Güvenlik: Admin panelindeki token'ı alıyoruz
        var token = localStorage.getItem('lab_token');
        if(!token) {
            alert("Bu belgeyi görüntülemek için giriş yapmalısınız.");
            window.location.href = '/';
            return;
        }

        var recordId = "{{ $id }}"; // Laravel'den gelen ID

        // API'den tek bir kaydın bilgilerini çek
        $.ajax({
            url: '/api/sonuclar/' + recordId,
            type: 'GET',
            headers: { 'Authorization': 'Bearer ' + token },
            success: function(response) {
                var date = new Date(response.created_at).toLocaleDateString('tr-TR');
                
                // HTML içindeki alanlara verileri yazdır
                $('#pName').text(response.patient.full_name);
                $('#pTc').text(response.patient.identity_number);
                $('#pBarcode').text(response.barcode_number);
                $('#pDate').text(date);
                $('#pTestName').text(response.test_name);
                $('#pDetails').text(response.result_details);
                
                // Belge yüklendikten yarım saniye sonra yazdırma ekranını otomatik aç
                setTimeout(function() {
                    window.print();
                }, 500);
            },
            error: function(xhr) {
                if (xhr.status === 401 || xhr.status === 403) {
                    alert('Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.');
                    localStorage.removeItem('lab_token');
                    window.location.href = '/';
                    return;
                }
                alert('Belge bilgileri yüklenirken bir hata oluştu!');
            }
        });
    });
</script>
</body>
</html>
