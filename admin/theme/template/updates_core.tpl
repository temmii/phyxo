{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Updates'|translate}</a></li>
    <li class="breadcrumb-item">Phyxo</li>
{/block}

{block name="footer_assets" prepend}
    <script>
     $(function() {
	 $('input[name="submit"]').click(function() {
	     if(!confirm('{'Are you sure?'|translate}')) {
		 return false;
	     }
	     $(this).hide();
	     $('.autoupdate_bar').show();
	 });
	 $('[name="understand"]').click(function() {
	     $('[name="submit"]').attr('disabled', !this.checked);
	 });
     });
    </script>
{/block}

{block name="content"}
    {if $STEP == 0}
	{if $UPGRADE_ERROR}
	    <p>{$UPGRADE_ERROR}</p>
	{elseif $CHECK_VERSION}
	    <p>{'You are running the latest version of Phyxo.'|translate}</p>
	{elseif $DEV_VERSION}
	    <p>{'You are running on development sources, no check possible.'|translate}</p>
	{else}
	    <p>{'Check for upgrade failed for unknown reasons.'|translate}</p>
	{/if}
    {/if}

    {if $STEP == 1}
	<h4>{'Two updates are available'|translate}:</h4>
	<p>
	    <ul>
		<li><a href="{$U_UPDATE_MINOR}"><strong>{'Update to Phyxo %s'|translate:$MINOR_VERSION}</strong></a>: {'This is a minor update, with only bug corrections.'|translate}</li>
		<li><a href="{$U_UPDATE_MAJOR}"><strong>{'Update to Phyxo %s'|translate:$MAJOR_VERSION}</strong></a>: {'This is a major update, with <a href="%s">new exciting features</a>.'|translate:$RELEASE_URL} {'Some themes and plugins may be not available yet.'|translate}</li>
	    </ul>
	</p>
	<p>{'You can update to Phyxo %s directly, without upgrading to Phyxo %s (recommended).'|translate:$MAJOR_VERSION:$MINOR_VERSION}</p>
    {/if}

    {if $STEP == 2}
	<p>
	    {'A new version of Phyxo is available.'|translate}<br>
	    {'This is a minor update, with only bug corrections.'|translate}
	</p>
	<form action="" method="post">
	    <p><input type="submit" class="btn btn-submit" name="submit" value="{'Update to Phyxo %s'|translate:$UPGRADE_TO}"></p>
	    <p class="autoupdate_bar" style="display:none;">&nbsp; {'Update in progress... Please wait.'|translate}</p>
	    <p><input type="hidden" name="upgrade_to" value="{$UPGRADE_TO}"></p>
	</form>
    {/if}

    {if $STEP == 3}
	<p>
	    {'A new version of Phyxo is available.'|translate}<br>
	    {'This is a major update, with <a href="%s">new exciting features</a>.'|translate:$RELEASE_URL} {'Some themes and plugins may be not available yet.'|translate}
	</p>
	<form action="" method="post">

	    {counter assign=i}
	    <fieldset>
		<legend>{'Update to Phyxo %s'|translate:$UPGRADE_TO}</legend>
		{if !empty($missing.plugins)}
		    <p><i>{'Following plugins may not be compatible with the new version of Phyxo:'|translate}</i></p>
		    <p><ul>{foreach from=$missing.plugins item=plugin}<li><a href="{$plugin.uri}" class="externalLink">{$plugin.name}</a></li>{/foreach}</ul><br></p>
		{/if}
		{if !empty($missing.themes)}
		    <p><i>{'Following themes may not be compatible with the new version of Phyxo:'|translate}</i></p>
		    <p><ul>{foreach from=$missing.themes item=theme}<li><a href="{$theme.uri}" class="externalLink">{$theme.name}</a></li>{/foreach}</ul><br></p>
		{/if}
		<p>
		    {if !empty($missing.plugins) or !empty($missing.themes)}
			<p><label><input type="checkbox" name="understand"> &nbsp;{'I decide to update anyway'|translate}</label></p>
		    {/if}
		    <p><input type="submit" name="submit" class="btn btn-submit" value="{'Update to Phyxo %s'|translate:$UPGRADE_TO}" {if !empty($missing.plugins) or !empty($missing.themes)}disabled="disabled"{/if}>
		    </p>
		    <p class="autoupdate_bar" style="display:none;">&nbsp; {'Update in progress... Please wait.'|translate}</p>
	    </fieldset>

	    <p><input type="hidden" name="upgrade_to" value="{$UPGRADE_TO}"></p>
	</form>
    {/if}
{/block}
