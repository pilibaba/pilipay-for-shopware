{extends file='parent:frontend/checkout/cart.tpl' }

{block name='frontend_checkout_actions_confirm' append}
    <a href="{url controller='paymentpilipay' action='payment' sUseSSL=true}" class="btn is--primary is--primary_yellow is--icon-right" title="{"{s name="LogoPilibabaPayment"}{/s}"|escape}"
       style="clear: right; float: right; background: #ffcc00 !important; border: 1px solid #dfb408 !important;">
        <i class="icon--arrow-right"></i>
        <img src="{link file="frontend/_resources/images/checkout2.png" fullPath}" alt="{s name="LogoPilibabaPayment"}{/s}" style="height: 36px;" />
    </a>
{/block}

{block name='frontend_checkout_actions_confirm_bottom_checkout' append}
    <a href="{url controller='paymentpilipay' action='payment' sUseSSL=true}" class="btn is--primary is--primary_yellow is--icon-right" title="{"{s name="LogoPilibabaPayment"}{/s}"|escape}"
       style="clear: right; float: right; background: #ffcc00 !important; border: 1px solid #dfb408 !important;">
        <i class="icon--arrow-right"></i>
        <img src="{link file="frontend/_resources/images/checkout2.png" fullPath}" alt="{s name="LogoPilibabaPayment"}{/s}" style="height: 36px;" />
    </a>
{/block}