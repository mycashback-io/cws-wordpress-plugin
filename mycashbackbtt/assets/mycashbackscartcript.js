jQuery(document).ready(function($){
    function get_items_in_cart(){
        var items = [];
        $.get( 
            '/mycashbackbtt/wp-json/wc/store/cart', // location of your php script
            function( data ){  // a function to deal with the returned information
                for (var i=0; i<data.items.length; i++){
                    items.push(data.items[i].id);
                }
                edit_cart_with_cashback(items);
            }
        );
    }
    function edit_cart_with_cashback(items){
        $.post( 
            '/mycashbackbtt/wp-json/cashback/v1/cart', // location of your php script
            { items: items },
            function( data ){  // a function to deal with the returned information
                //$( 'body ').append( data );
                //console.log(data);
                $(".woocommerce-cart-form__contents thead tr .product-price").after("<th class='product-cashback'>Cashback(%)</th>");
                $(".woocommerce-cart-form__contents thead tr .product-subtotal").after("<th class='product-cashback-amount'>Cashback</th>");
                $('.woocommerce-cart-form__contents > tbody  > tr').each(function(index) { 
                    if (index < data.length) {
                        var purchaseAmount = $(this).children(".product-quantity").children().children().next().val();
                        var cashbackValue = ((Number($(this).children(".product-price").children().text().substring(1)) * data[index]) / 100) * purchaseAmount;
                        $(this).children(".product-price").after('<td class="product-cashback" data-title="Cashback"><span class="woocommerce-Cashback-amount amount"><bdi>'+data[index]+'</bdi>%</span></td>');
                        $(this).children(".product-subtotal").after('<td class="product-cashback-amount" data-title="Cashback Amount"><span class="woocommerce-Cashback-value amount">฿<bdi>'+cashbackValue.toFixed(2)+'</bdi></span></td>');
                    } else{
                        var cashbackValue = "฿0.00";
                        $(this).children(".product-price").after('<td class="product-cashback" data-title="Cashback"><span class="woocommerce-Cashback-amount amount"><bdi>0</bdi>%</span></td>');
                        $(this).children(".product-subtotal").after('<td class="product-cashback-amount" data-title="Cashback Amount"><span class="woocommerce-Cashback-value amount"><bdi>'+cashbackValue+'</bdi></span></td>');
                    }
                });
            }
        );
        console.log(items)
    }

    get_items_in_cart();
});