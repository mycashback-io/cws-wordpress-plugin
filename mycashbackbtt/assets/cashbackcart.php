<?php
    global $woocommerce;
    $items = $woocommerce->cart->get_cart();
    $data = [ 'a', 'b', 'c' ];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode( $data );
?>