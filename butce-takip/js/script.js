jQuery(document).ready(function($) {
    // Form gönderimi
    $('#yeni-islem-formu').on('submit', function(e) {
        e.preventDefault();
        
        // Form verilerini al
        var formData = {
            action: 'kaydet_butce_islemi',
            nonce: butceTakip.nonce,
            islem_tipi: $('select[name="islem_tipi"]').val(),
            kategori: $('select[name="kategori"]').val(),
            tutar: $('input[name="tutar"]').val(),
            tarih: $('input[name="tarih"]').val(),
            aciklama: $('textarea[name="aciklama"]').val()
        };

        // Form submit butonunu devre dışı bırak
        var submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true).text('Kaydediliyor...');

        $.ajax({
            url: butceTakip.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Başarılı işlem
                    alert(response.data.message);
                    $('#yeni-islem-formu')[0].reset();
                    verileriGuncelle();
                } else {
                    // Hata durumu
                    var errorMessage = response.data.message;
                    if (WP_DEBUG && response.data.debug) {
                        errorMessage += '\n\nHata detayı: ' + JSON.stringify(response.data.debug);
                    }
                    alert(errorMessage);
                }
            },
            error: function(xhr, status, error) {
                alert('Sistem hatası: ' + error);
                console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
            },
            complete: function() {
                // Form submit butonunu tekrar aktif et
                submitButton.prop('disabled', false).text('İşlemi Kaydet');
            }
        });
    });

     // İşlem tipi değiştiğinde kategorileri filtrele
    $('select[name="islem_tipi"]').on('change', function() {
        const secilenTip = $(this).val();
        const kategoriSelect = $('select[name="kategori"]');
        
        // Önce tüm kategorileri göster
        kategoriSelect.find('option').show();
        
        if (secilenTip) {
            // Seçilen tipe uygun olmayan kategorileri gizle
            kategoriSelect.find('option').not('[data-tip="' + secilenTip + '"]').not(':first').hide();
        }
        
        // Kategori seçimini sıfırla
        kategoriSelect.val('');
    });
});

// Form gönderiminde hata kontrolü
$('#yeni-islem-formu').on('submit', function(e) {
    const islemTipi = $('select[name="islem_tipi"]').val();
    const kategori = $('select[name="kategori"]').val();
    
    if (!islemTipi) {
        alert('Lütfen bir işlem tipi seçin.');
        e.preventDefault();
        return false;
    }
    
    if (!kategori) {
        alert('Lütfen bir kategori seçin.');
        e.preventDefault();
        return false;
    }
});

// Sayfa yüklendiğinde işlem tipine göre kategorileri filtrele
$(document).ready(function() {
    const secilenTip = $('select[name="islem_tipi"]').val();
    if (secilenTip) {
        $('select[name="islem_tipi"]').trigger('change');
    }
});

    // Verileri güncelleme fonksiyonu
    function verileriGuncelle() {
        $.ajax({
            url: butceTakip.ajaxurl,
            type: 'POST',
            data: {
                action: 'getir_butce_verileri',
                nonce: butceTakip.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Özet kartlarını güncelle
                    $('#toplam-gelir').text('₺' + formatNumber(response.data.ozet.toplam_gelir));
                    $('#toplam-gider').text('₺' + formatNumber(response.data.ozet.toplam_gider));
                    $('#net-bakiye').text('₺' + formatNumber(response.data.ozet.net_bakiye));

                    // Grafiği güncelle
                    updateChart(response.data.grafik);

                    // Son işlemleri güncelle
                    updateSonIslemler(response.data.son_islemler);
                }
            },
            error: function(xhr, status, error) {
                console.error('Veri güncelleme hatası:', error);
            }
        });
    }

   // Grafiği güncelleme fonksiyonu
function updateChart(data) {
    if (window.butceGrafik) {
        window.butceGrafik.destroy();
    }

    const ctx = document.getElementById('butce-grafik').getContext('2d');
    
    // Yüksek DPI için canvas ayarları
    const dpr = window.devicePixelRatio || 1;
    const canvas = ctx.canvas;
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    
    // Canvas boyutunu 2 katına çıkar (daha keskin görüntü için)
    const parent = canvas.parentNode;
    canvas.width = parent.offsetWidth * 2;
    canvas.height = parent.offsetHeight * 2;
    ctx.scale(2, 2);

    window.butceGrafik = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Gelir',
                    backgroundColor: 'rgba(76, 175, 80, 0.8)',
                    borderColor: '#4CAF50',
                    borderWidth: 1,
                    data: data.gelirler,
                    borderRadius: 6,
                    barThickness: 30,
                    maxBarThickness: 35
                },
                {
                    label: 'Gider',
                    backgroundColor: 'rgba(244, 67, 54, 0.8)',
                    borderColor: '#f44336',
                    borderWidth: 1,
                    data: data.giderler,
                    borderRadius: 6,
                    barThickness: 30,
                    maxBarThickness: 35
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            devicePixelRatio: 2,
            layout: {
                padding: {
                    top: 20,
                    right: 25,
                    bottom: 20,
                    left: 25
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        lineWidth: 1
                    },
                    ticks: {
                        font: {
                            size: 13,
                            weight: '500'
                        },
                        padding: 10,
                        callback: function(value) {
                            return '₺' + formatNumber(value);
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 13,
                            weight: '500'
                        },
                        padding: 10
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'center',
                    labels: {
                        padding: 20,
                        font: {
                            size: 14,
                            weight: '600'
                        },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    padding: 12,
                    cornerRadius: 6,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ₺' + formatNumber(context.raw);
                        }
                    }
                }
            },
            animation: {
                duration: 750,
                easing: 'easeInOutQuart'
            }
        }
    });
}

    // Son işlemleri güncelleme fonksiyonu
    function updateSonIslemler(islemler) {
        const container = $('#son-islemler-listesi');
        container.empty();

        if (islemler.length === 0) {
            container.append('<div class="no-islem">Henüz işlem bulunmamaktadır.</div>');
            return;
        }

        islemler.forEach(function(islem) {
            const islemHtml = `
                <div class="islem-item ${islem.islem_tipi}">
                    <div class="islem-detay">
                        <strong>${islem.kategori}</strong>
                        <span>${formatDate(islem.tarih)}</span>
                        <p>${islem.aciklama || ''}</p>
                    </div>
                    <div class="islem-tutar">
                        ${islem.islem_tipi === 'gelir' ? '+' : '-'}₺${formatNumber(islem.tutar)}
                    </div>
                </div>
            `;
            container.append(islemHtml);
        });
    }

    // Sayı formatla
    function formatNumber(number) {
        return new Intl.NumberFormat('tr-TR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(number);
    }

    // Tarih formatla
    function formatDate(dateString) {
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        return new Date(dateString).toLocaleDateString('tr-TR', options);
    }

    // Sayfa yüklendiğinde verileri getir
    verileriGuncelle();

    // Her 30 saniyede bir verileri güncelle
    setInterval(verileriGuncelle, 30000);
});