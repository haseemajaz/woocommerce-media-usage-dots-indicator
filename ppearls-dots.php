<?php
/**
 * Plugin Name: Pins & Pearls – Media Dot Indicator
 * Description: Shows a green dot on media library images used in WooCommerce products.
 * Version: 1.1
 * Author: Custom
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'save_post_product',          'ppearls_bust_dot_cache' );
add_action( 'delete_post',                'ppearls_bust_dot_cache' );
add_action( 'woocommerce_update_product', 'ppearls_bust_dot_cache' );
function ppearls_bust_dot_cache() {
    delete_transient( 'ppearls_img_map' );
}

function ppearls_get_image_map(): array {
    $cached = get_transient( 'ppearls_img_map' );
    if ( $cached !== false ) return $cached;

    global $wpdb;
    $map = [];

    $rows = $wpdb->get_results(
        "SELECT pm.meta_value AS att_id, p.ID AS pid, p.post_title AS name, p.post_status AS status
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_thumbnail_id'
           AND p.post_type = 'product'
           AND p.post_status IN ('publish','draft','private')"
    );
    foreach ( $rows as $r ) {
        $id = (int) $r->att_id;
        if ( $id ) $map[$id][] = [
            'name'   => $r->name,
            'url'    => get_edit_post_link( $r->pid, 'raw' ),
            'status' => $r->status,
        ];
    }

    $rows = $wpdb->get_results(
        "SELECT pm.meta_value AS gallery, p.ID AS pid, p.post_title AS name, p.post_status AS status
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_product_image_gallery'
           AND pm.meta_value != ''
           AND p.post_type = 'product'
           AND p.post_status IN ('publish','draft','private')"
    );
    foreach ( $rows as $r ) {
        foreach ( array_filter( array_map( 'intval', explode( ',', $r->gallery ) ) ) as $id ) {
            $map[$id][] = [
                'name'   => $r->name,
                'url'    => get_edit_post_link( $r->pid, 'raw' ),
                'status' => $r->status,
            ];
        }
    }

    foreach ( $map as $id => &$list ) {
        $seen = []; $out = [];
        foreach ( $list as $p ) {
            if ( ! in_array( $p['url'], $seen, true ) ) {
                $seen[] = $p['url'];
                $out[]  = $p;
            }
        }
        $list = $out;
    }
    unset( $list );

    set_transient( 'ppearls_img_map', $map, HOUR_IN_SECONDS );
    return $map;
}

add_action( 'admin_footer', 'ppearls_dot_footer' );
function ppearls_dot_footer() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->base !== 'upload' ) return;

    $map      = ppearls_get_image_map();
    $safe_map = [];
    foreach ( $map as $id => $products ) {
        $safe_map[ $id ] = array_map( function( $p ) {
            return [
                'name'   => esc_html( $p['name'] ),
                'url'    => esc_url( $p['url'] ),
                'status' => esc_attr( $p['status'] ),
            ];
        }, $products );
    }

    $json = wp_json_encode( $safe_map );
    ?>
<style id="ppearls-css">
.attachment { position: relative !important; }
.attachment-preview { overflow: visible !important; }
ul.attachments { overflow: visible !important; }
.media-frame-content { overflow: visible !important; }
.attachments-browser { overflow: visible !important; }
.ppearls-dot {
    position: absolute !important;
    top: 4px !important;
    right: 4px !important;
    width: 16px !important;
    height: 16px !important;
    background: #16a34a !important;
    border: 2px solid #fff !important;
    border-radius: 50% !important;
    z-index: 9999 !important;
    cursor: pointer !important;
    box-shadow: 0 0 0 1px rgba(0,0,0,0.3) !important;
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}
.ppearls-dot:hover { transform: scale(1.3); }
/* Tooltip is now appended to body — not a child of the dot */
.ppearls-tip {
    display: none;
    position: fixed;
    min-width: 190px;
    max-width: 280px;
    background: #111827;
    color: #f3f4f6;
    border: 1px solid #16a34a;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 12px;
    line-height: 1.6;
    z-index: 99999;
    box-shadow: 0 6px 20px rgba(0,0,0,0.5);
    pointer-events: auto;
}
.ppearls-tip-hdr {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #4ade80;
    margin-bottom: 5px;
    padding-bottom: 4px;
    border-bottom: 1px solid #1f2937;
}
.ppearls-tip a {
    display: block;
    color: #d1d5db;
    text-decoration: none;
    padding: 2px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ppearls-tip a:hover { color: #4ade80; }
.ppearls-badge { font-size: 9px; color: #6b7280; margin-left: 3px; }
</style>
<script id="ppearls-js">
(function() {
    var MAP = <?php echo $json; ?>;
    var stamped = new WeakSet();

    // Single shared tooltip appended to <body>
    var tip = document.createElement('div');
    tip.className = 'ppearls-tip';
    document.body.appendChild(tip);

    var hideTimer = null;

    function showTip(dot, prods) {
        clearTimeout(hideTimer);

        var hdr = prods.length === 1 ? 'Used in 1 product' : 'Used in ' + prods.length + ' products';
        var links = prods.map(function(p) {
            var badge = p.status !== 'publish' ? '<span class="ppearls-badge">(' + p.status + ')</span>' : '';
            return '<a href="' + p.url + '" target="_blank">' + p.name + badge + '</a>';
        }).join('');
        tip.innerHTML = '<div class="ppearls-tip-hdr">' + hdr + '</div>' + links;

        var r = dot.getBoundingClientRect();
        tip.style.top  = (r.bottom + 6) + 'px';
        tip.style.left = Math.max(8, r.left - 160) + 'px';
        tip.style.display = 'block';
    }

    function schedulHide() {
        hideTimer = setTimeout(function() {
            tip.style.display = 'none';
        }, 120);
    }

    // Keep tooltip visible when mouse is over it
    tip.addEventListener('mouseenter', function() { clearTimeout(hideTimer); });
    tip.addEventListener('mouseleave', schedulHide);

    function stamp(el) {
        var id = parseInt(el.dataset.id, 10);
        if (!id || !MAP[id] || !MAP[id].length) return;
        if (stamped.has(el)) return;
        stamped.add(el);

        var dot = document.createElement('div');
        dot.className = 'ppearls-dot';

        var prods = MAP[id];

        dot.addEventListener('mouseenter', function() { showTip(dot, prods); });
        dot.addEventListener('mouseleave', schedulHide);

        el.appendChild(dot);
    }

    function scanAll() {
        document.querySelectorAll('.attachment[data-id]').forEach(stamp);
    }

    new MutationObserver(function(muts) {
        muts.forEach(function(m) {
            m.addedNodes.forEach(function(n) {
                if (n.nodeType !== 1) return;
                if (n.classList && n.classList.contains('attachment')) {
                    stamp(n);
                } else {
                    n.querySelectorAll && n.querySelectorAll('.attachment[data-id]').forEach(stamp);
                }
            });
        });
    }).observe(document.body, { childList: true, subtree: true });

    var hits = 0, ticks = 0;
    var poller = setInterval(function() {
        scanAll();
        ticks++;
        if (document.querySelectorAll('.attachment[data-id]').length > 0) hits++;
        if (hits >= 3 || ticks >= 50) clearInterval(poller);
    }, 400);

    document.addEventListener('DOMContentLoaded', scanAll);
    window.addEventListener('load', function() {
        scanAll();
        setTimeout(scanAll, 500);
        setTimeout(scanAll, 1500);
        setTimeout(scanAll, 3000);
    });

    window.ppearls_debug = function() {
        var els = document.querySelectorAll('.attachment[data-id]');
        console.log('Attachments found:', els.length);
        console.log('MAP keys:', Object.keys(MAP).length);
        if (els.length > 0) {
            var id = els[0].dataset.id;
            console.log('First ID:', id, '| In MAP?', !!MAP[id]);
        }
    };
    console.log('[ppearls] loaded OK — run ppearls_debug() to check');
}());
</script>
    <?php
}
