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
        
        // Tüm kategorileri gizle
        kategoriSelect.find('option').hide();
        kategoriSelect.find('option:first').show();
        
        if (secilenTip) {
            // Seçilen tipe uygun kategorileri göster
            kategoriSelect.find('option[data-tip="' + secilenTip + '"]').show();
        }
        
        // Seçili kategoriyi sıfırla
        kategoriSelect.val('');
    });

    // Sayfa yüklendiğinde kategorileri gizle
    $(document).ready(function() {
        const kategoriSelect = $('select[name="kategori"]');
        kategoriSelect.find('option').not(':first').hide();
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
        
        // Canvas piksel oranını ayarla
        const dpr = window.devicePixelRatio || 1;
        const canvas = ctx.canvas;
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';

        window.butceGrafik = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Gelir',
                        backgroundColor: '#4CAF50',
                        data: data.gelirler,
                        borderRadius: 4
                    },
                    {
                        label: 'Gider',
                        backgroundColor: '#f44336',
                        data: data.giderler,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                devicePixelRatio: dpr,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
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
                                size: 12
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                size: 14
                            }
                        }
                    },
                    tooltip: {
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₺' + formatNumber(context.raw);
                            }
                        }
                    }
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