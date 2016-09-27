{extends file='parent:frontend/checkout/ajax_cart.tpl' }

{block name='frontend_checkout_ajax_cart_open_basket' append}

    <a href="{url controller='paymentpilipay' action='payment' sUseSSL=true}" class="btn is--primary is--icon-right is--primary_yellow pilibaba_btn" title="{"{s name="LogoPilibabaPayment"}Logo 'Pilibaba payment'{/s}"|escape}">
        <i class="icon--arrow-right"></i>
        <img src="{link file="frontend/_resources/images/button1.png" fullPath}" alt="{s name="LogoPilibabaPayment"}{/s}">
    </a>
    <style type="text/css">
        a.pilibaba_btn{
            height: 38px; text-align: center;width:100%; background: #f4c51c !important; border: 1px solid #dfb408 !important; margin-top: 10px;
        }
        a.pilibaba_btn img{
            display: inline-block;float: none;height: 36px;
        }
        a.pilibaba_btn:hover{
            border: 1px solid #b28d08 !important;
        }
    </style>
{/block}
