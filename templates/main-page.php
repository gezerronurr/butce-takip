<div class="wrap butce-takip-container">
    <h1>Bütçe Takip</h1>
    <p>Gelir ve giderlerinizi kolayca takip edin.</p>

    <!-- Özet Kartları -->
    <div class="butce-ozet-kartlar">
        <div class="ozet-kart gelir">
            <h3>Toplam Gelir</h3>
            <h2 id="toplam-gelir">₺0</h2>
            <span>Son 30 gün</span>
        </div>
        <div class="ozet-kart gider">
            <h3>Toplam Gider</h3>
            <h2 id="toplam-gider">₺0</h2>
            <span>Son 30 gün</span>
        </div>
        <div class="ozet-kart bakiye">
            <h3>Net Bakiye</h3>
            <h2 id="net-bakiye">₺0</h2>
            <span>Son 30 gün</span>
        </div>
    </div>

    <!-- Grafik Alanı -->
<div class="grafik-alani">
    <h3>Son 6 Aylık Bütçe Özeti</h3>
    <div class="grafik-container">
        <canvas id="butce-grafik"></canvas>
    </div>
</div>

    <!-- Yeni İşlem Formu -->
<div class="islem-formu">
    <h3>Yeni İşlem Ekle</h3>
    <form id="yeni-islem-formu">
        <select name="islem_tipi" required>
            <option value="">İşlem Tipi Seçin</option>
            <option value="gelir">Gelir</option>
            <option value="gider">Gider</option>
        </select>

        <select name="kategori" required>
    <option value="">Kategori Seçin</option>
    <?php
    global $wpdb;
    $kategoriler = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}butce_kategoriler ORDER BY kategori_adi ASC"
    );
    
    if ($kategoriler) {
        foreach ($kategoriler as $kategori) {
            printf(
                '<option value="%s" data-tip="%s">%s</option>',
                esc_attr($kategori->kategori_adi),
                esc_attr($kategori->tur), // islem_tipi yerine tur kullanıyoruz
                esc_html($kategori->kategori_adi)
            );
        }
    }
    ?>
</select>

        <input type="number" name="tutar" step="0.01" min="0" required placeholder="Tutar (₺)">
        <input type="date" name="tarih" required value="<?php echo date('Y-m-d'); ?>">
        <textarea name="aciklama" placeholder="Açıklama (İsteğe bağlı)"></textarea>
        <button type="submit" class="button button-primary">İşlemi Kaydet</button>
    </form>
</div>

    <!-- Son İşlemler -->
    <div class="son-islemler">
        <div class="son-islemler-baslik">
            <h3>Son İşlemler</h3>
        </div>
        <div id="son-islemler-listesi">
            <!-- JavaScript ile doldurulacak -->
        </div>
    </div>
</div>