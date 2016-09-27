{extends file="frontend/index/index.tpl"}



{* Hide breadcrumb *}
{block name='frontend_index_breadcrumb'}
    <hr class="clear"/>
{/block}

{block name='frontend_index_content_left'}{/block}

{block name='frontend_index_content'}
    <div class="content is--wide apalogin">
            <div class="panel--body is--wide">
                <h1>{s name="pilibaba_error"}Error in the order{/s}</h1>
                    <fieldset>
                        <div class="action register--login-action">
                        </div>
                    </fieldset>
            </div>
    </div>
{/block}




