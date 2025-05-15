<?php
/*
Plugin Name: Bütçe Takip
Description: Gelir ve giderlerinizi kolayca takip edebileceğiniz bütçe yönetim sistemi
Version: 1.0
Author: Onur Gezer
*/

// Güvenlik kontrolü
if (!defined('ABSPATH')) exit;

// Plugin ana sınıfı
class ButceTakipSistemi {
    private $plugin_path;
    private $plugin_url;

    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        // Admin menüsü ve sayfa işlemleri
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));

        // Script ve stil dosyaları
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Plugin aktivasyon ve deaktivasyon hooks
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));

        // AJAX işleyicileri
        add_action('wp_ajax_kaydet_butce_islemi', array($this, 'kaydet_butce_islemi'));
        add_action('wp_ajax_getir_butce_verileri', array($this, 'getir_butce_verileri'));
    }

    // Admin menüsü oluşturma
    public function add_admin_menu() {
        $hook = add_menu_page(
            'Bütçe Takip',
            'Bütçe Takip',
            'manage_options',
            'butce-takip',
            array($this, 'main_page_content'),
            'dashicons-chart-bar',
            30
        );
        
        // Sadece plugin sayfasında scriptleri yükle
        add_action('load-' . $hook, array($this, 'enqueue_scripts'));
    }

    // Ayarları başlat
    public function init_settings() {
        register_setting('butce_takip_options', 'butce_takip_settings');
    }

    // Gerekli script ve stilleri yükleme
    public function enqueue_scripts($hook) {
        // Sadece plugin sayfasında yükle
        if ($hook != 'toplevel_page_butce-takip') {
            return;
        }

        // CSS
        wp_enqueue_style(
            'butce-takip-style',
            $this->plugin_url . 'css/style.css',
            array(),
            filemtime($this->plugin_path . 'css/style.css')
        );

        // JavaScript
        wp_enqueue_script('jquery');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js');
        wp_enqueue_script(
            'butce-takip-script',
            $this->plugin_url . 'js/script.js',
            array('jquery', 'chart-js'),
            filemtime($this->plugin_path . 'js/script.js'),
            true
        );

        // AJAX URL ve nonce değerini JavaScript'e aktar
        wp_localize_script('butce-takip-script', 'butceTakip', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('butce_takip_nonce')
        ));
    }

    // Plugin aktivasyonu
public function activate_plugin() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // İşlemler tablosu
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}butce_islemler (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        islem_tipi varchar(50) NOT NULL,
        kategori varchar(100) NOT NULL,
        tutar decimal(10,2) NOT NULL,
        tarih date NOT NULL,
        aciklama text,
        olusturma_tarihi timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    // Kategoriler tablosu
    $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}butce_kategoriler (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        kategori_adi varchar(100) NOT NULL,
        tur varchar(50) NOT NULL,
        olusturma_tarihi timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY kategori_adi (kategori_adi)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Varsayılan kategorileri ekle
    $varsayilan_kategoriler = array(
        // Gelir Kategorileri
        array('Maaş', 'gelir'),
        array('Ek Gelir', 'gelir'),
        array('Yatırım Geliri', 'gelir'),
        array('Kira Geliri', 'gelir'),
        array('Freelance Gelir', 'gelir'),
        array('Prim', 'gelir'),
        array('Diğer Gelirler', 'gelir'),
        
        // Gider Kategorileri
        array('Kira', 'gider'),
        array('Market', 'gider'),
        array('Faturalar', 'gider'),
        array('Ulaşım', 'gider'),
        array('Sağlık', 'gider'),
        array('Eğitim', 'gider'),
        array('Eğlence', 'gider'),
        array('Alışveriş', 'gider'),
        array('Yemek', 'gider'),
        array('Sigorta', 'gider'),
        array('Aidat', 'gider'),
        array('Elektronik', 'gider'),
        array('Spor', 'gider'),
        array('Bakım', 'gider'),
        array('Hediye', 'gider'),
        array('Diğer Giderler', 'gider')
    );

    foreach ($varsayilan_kategoriler as $kategori) {
        $wpdb->replace(
            $wpdb->prefix . 'butce_kategoriler',
            array(
                'kategori_adi' => $kategori[0],
                'tur' => $kategori[1]
            ),
            array('%s', '%s')
        );
    }
}

    // Plugin deaktivasyonu
    public function deactivate_plugin() {
        // Şimdilik bir şey yapmıyoruz
    }

    // Yeni işlem kaydetme (AJAX)
public function kaydet_butce_islemi() {
    try {
        // Nonce kontrolü
        if (!check_ajax_referer('butce_takip_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Güvenlik doğrulaması başarısız.'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Yetkiniz bulunmamaktadır.'));
            return;
        }
        
        global $wpdb;
        
        $data = array(
            'islem_tipi' => isset($_POST['islem_tipi']) ? sanitize_text_field($_POST['islem_tipi']) : '',
            'kategori' => isset($_POST['kategori']) ? sanitize_text_field($_POST['kategori']) : '',
            'tutar' => isset($_POST['tutar']) ? floatval(str_replace(',', '.', $_POST['tutar'])) : 0,
            'tarih' => isset($_POST['tarih']) ? sanitize_text_field($_POST['tarih']) : '',
            'aciklama' => isset($_POST['aciklama']) ? sanitize_textarea_field($_POST['aciklama']) : ''
        );

        // Kategori kontrolü
        $kategori_kontrol = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}butce_kategoriler WHERE kategori_adi = %s AND tur = %s",
            $data['kategori'],
            $data['islem_tipi']
        ));

        if (!$kategori_kontrol) {
            wp_send_json_error(array('message' => 'Geçersiz kategori seçimi.'));
            return;
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'butce_islemler',
            $data,
            array('%s', '%s', '%f', '%s', '%s')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Veritabanı hatası: ' . $wpdb->last_error));
            return;
        }

        wp_send_json_success(array(
            'message' => 'İşlem başarıyla kaydedildi.',
            'id' => $wpdb->insert_id
        ));

    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Beklenmeyen bir hata oluştu.'));
    }
}
            
            // POST verilerini al ve temizle
            $data = array(
                'islem_tipi' => isset($_POST['islem_tipi']) ? sanitize_text_field($_POST['islem_tipi']) : '',
                'kategori' => isset($_POST['kategori']) ? sanitize_text_field($_POST['kategori']) : '',
                'tutar' => isset($_POST['tutar']) ? str_replace(',', '.', $_POST['tutar']) : 0,
                'tarih' => isset($_POST['tarih']) ? sanitize_text_field($_POST['tarih']) : '',
                'aciklama' => isset($_POST['aciklama']) ? sanitize_textarea_field($_POST['aciklama']) : ''
            );

            // Veri doğrulama
            if (empty($data['islem_tipi'])) {
                wp_send_json_error(array(
                    'message' => 'İşlem tipi boş olamaz.',
                    'debug' => 'Empty transaction type'
                ));
                return;
            }

            if (empty($data['kategori'])) {
                wp_send_json_error(array(
                    'message' => 'Kategori boş olamaz.',
                    'debug' => 'Empty category'
                ));
                return;
            }

            if (empty($data['tutar']) || !is_numeric($data['tutar'])) {
                wp_send_json_error(array(
                    'message' => 'Geçerli bir tutar giriniz.',
                    'debug' => 'Invalid amount'
                ));
                return;
            }

            if (empty($data['tarih']) || !strtotime($data['tarih'])) {
                wp_send_json_error(array(
                    'message' => 'Geçerli bir tarih giriniz.',
                    'debug' => 'Invalid date'
                ));
                return;
            }

            // Veritabanı işlemi
            $insert_data = array(
                'islem_tipi' => $data['islem_tipi'],
                'kategori' => $data['kategori'],
                'tutar' => floatval($data['tutar']),
                'tarih' => $data['tarih'],
                'aciklama' => $data['aciklama']
            );

            $insert_format = array(
                '%s', // islem_tipi
                '%s', // kategori
                '%f', // tutar
                '%s', // tarih
                '%s'  // aciklama
            );

            // Hata raporlamayı aktifleştir
            $wpdb->show_errors();

            $result = $wpdb->insert(
                $wpdb->prefix . 'butce_islemler',
                $insert_data,
                $insert_format
            );

            if ($result === false) {
                wp_send_json_error(array(
                    'message' => 'Veritabanı hatası: ' . $wpdb->last_error,
                    'debug' => array(
                        'query' => $wpdb->last_query,
                        'error' => $wpdb->last_error,
                        'data' => $insert_data
                    )
                ));
                return;
            }

            wp_send_json_success(array(
                'message' => 'İşlem başarıyla kaydedildi.',
                'id' => $wpdb->insert_id,
                'data' => $insert_data
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Beklenmeyen bir hata oluştu.',
                'debug' => $e->getMessage()
            ));
        }
    }

    // Bütçe verilerini getirme (AJAX)
    public function getir_butce_verileri() {
        check_ajax_referer('butce_takip_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Yetkiniz bulunmamaktadır.'));
        }
        
        global $wpdb;
        
        // Son 30 günlük özet
        $baslangic_tarihi = date('Y-m-d', strtotime('-30 days'));
        
        $ozet = array(
            'toplam_gelir' => 0,
            'toplam_gider' => 0,
            'net_bakiye' => 0
        );
        
        // Gelirleri hesapla
        $gelirler = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(tutar), 0) FROM {$wpdb->prefix}butce_islemler 
            WHERE islem_tipi = 'gelir' AND tarih >= %s",
            $baslangic_tarihi
        ));
        
        // Giderleri hesapla
        $giderler = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(tutar), 0) FROM {$wpdb->prefix}butce_islemler 
            WHERE islem_tipi = 'gider' AND tarih >= %s",
            $baslangic_tarihi
        ));
        
        $ozet['toplam_gelir'] = floatval($gelirler);
        $ozet['toplam_gider'] = floatval($giderler);
        $ozet['net_bakiye'] = $ozet['toplam_gelir'] - $ozet['toplam_gider'];
        
        // Grafik verileri
        $son_6_ay = array();
        for ($i = 5; $i >= 0; $i--) {
            $ay = date('Y-m', strtotime("-$i months"));
            $son_6_ay[] = $ay;
        }
        
        $grafik = array(
            'labels' => array(),
            'gelirler' => array(),
            'giderler' => array()
        );
        
        $aylar_tr = array(
            'January' => 'Ocak',
            'February' => 'Şubat',
            'March' => 'Mart',
            'April' => 'Nisan',
            'May' => 'Mayıs',
            'June' => 'Haziran',
            'July' => 'Temmuz',
            'August' => 'Ağustos',
            'September' => 'Eylül',
            'October' => 'Ekim',
            'November' => 'Kasım',
            'December' => 'Aralık'
        );
        
        foreach ($son_6_ay as $ay) {
            $ay_adi = date('F', strtotime($ay));
            $grafik['labels'][] = $aylar_tr[$ay_adi];
            
            // Aylık gelirler
            $aylik_gelir = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(tutar), 0) FROM {$wpdb->prefix}butce_islemler 
                WHERE islem_tipi = 'gelir' AND DATE_FORMAT(tarih, '%Y-%m') = %s",
                $ay
            ));
            
            // Aylık giderler
            $aylik_gider = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(tutar), 0) FROM {$wpdb->prefix}butce_islemler 
                WHERE islem_tipi = 'gider' AND DATE_FORMAT(tarih, '%Y-%m') = %s",
                $ay
            ));
            
            $grafik['gelirler'][] = floatval($aylik_gelir);
            $grafik['giderler'][] = floatval($aylik_gider);
        }
        
        // Son işlemler
        $son_islemler = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}butce_islemler 
            ORDER BY tarih DESC, id DESC 
            LIMIT 5"
        );
        
        // Kategorileri getir
        $kategoriler = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}butce_kategoriler 
            ORDER BY kategori_adi ASC"
        );
        
       wp_send_json_success(array(
            'ozet' => $ozet,
            'grafik' => $grafik,
            'son_islemler' => $son_islemler,
            'kategoriler' => $kategoriler
        ));
    }

    // Ana sayfa içeriği
    public function main_page_content() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Bu sayfaya erişim yetkiniz bulunmamaktadır.'));
        }
        
        // Template dosyasını yükle
        if (file_exists($this->plugin_path . 'templates/main-page.php')) {
            include $this->plugin_path . 'templates/main-page.php';
        } else {
            echo 'Template dosyası bulunamadı.';
        }
    }
}

// Plugin örneğini oluştur
$butce_takip = new ButceTakipSistemi();