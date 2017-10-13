{extends file="__layout.tpl"}

{block name="context_wrapper"}{/block}

{block name="breadcrumb"}
    <h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'Notification'|translate}</h2>
{/block}

{block name="head_html"}
    <link rel="alternate" type="application/rss+xml" title="{'Photos only RSS feed'|translate}" href="{$U_FEED_IMAGE_ONLY}">
    <link rel="alternate" type="application/rss+xml" title="{'Complete RSS feed (photos, comments)'|translate}" href="{$U_FEED}">
{/block}

{block name="main-content"}
    {include file="_infos_errors.tpl"}

    <div class="notification">
	<p>{'The RSS notification feed provides notification on news from this website : new photos, updated albums, new comments. Use a RSS feed reader.'|translate}</p>

	<dl>
	    <dt>
		<a href="{$U_FEED_IMAGE_ONLY}">{'Photos only RSS feed'|translate}</a><br><br>
	    </dt>
	    <dt>
		<a href="{$U_FEED}">{'Complete RSS feed (photos, comments)'|translate}</a>
	    </dt>
	</dl>
    </div>
{/block}
