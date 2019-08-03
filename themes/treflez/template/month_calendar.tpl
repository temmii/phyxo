{extends file="__layout.tpl"}

{block name="head_assets" append}
    {if isset($chronology_calendar.month_view)}
	<style>
	 #calMonth .calImg{
	     width:{$chronology_calendar.month_view.CELL_WIDTH}px;height:{$chronology_calendar.month_view.CELL_HEIGHT}px
	 }
	 #calMonth td{
	     width:{$chronology_calendar.month_view.CELL_WIDTH}px;height:{$chronology_calendar.month_view.CELL_HEIGHT + 29}px
	 }
	</style>
    {/if}
{/block}

{block name="content"}
    {include file="category_nav.tpl"}

    <div id="calendar-select" class="btn-group">
        <button id="calendar-view" type="button" class="btn btn-primary btn-raised dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            {foreach $chronology_views as $view}
		{if $view.SELECTED}{$view.CONTENT}{/if}
	    {/foreach}
        </button>
        <div class="dropdown-menu" aria-labelledby="calendar-view">
            {foreach $chronology_views as $view}
                <a class="dropdown-item{if $view.SELECTED} active{/if}" href="{$view.VALUE}">{$view.CONTENT}</a>
            {/foreach}
        </div>
    </div>

    {if !empty($chronology_navigation_bars)}
	{foreach $chronology_navigation_bars as $bar}
	    <div id="calendar-nav">
		{if isset($bar.previous)}
		    <a id="calendar-prev" class="btn btn-secondary pull-left" href="{$bar.previous.URL}">
			<span class="fa fa-arrow-left"></span>
			{$bar.previous.LABEL}
		    </a>
		{/if}
		{if !empty($bar.items)}
		    <div id="calendar-nav-buttons" class="btn-group" role=group">
			{assign var=item_type value=reset($bar.items)} {* avoid strict standards warning with nested resets *}
			{if gettype(reset($item_type)) === 'string' || (!isset($bar.previous) && !isset($bar.next))}
			    {foreach $bar.items as $item}
				{if !isset($item.URL)}
				    <span class="btn btn-secondary disabled">{$item.LABEL}</span>
				{else}
				    <a class="btn btn-secondary" {if isset($item.NB_IMAGES)}title="{$item.NB_IMAGES|translate_dec:'%d photo':'%d photos'}"{/if} href="{$item.URL}">{$item.LABEL}</a>
				{/if}
			    {/foreach}
			{else}
			    <table class="table table-sm table-bordered">
				<tbody>
				    {assign var=i value=0}
				    {foreach $bar.items as $item}
					{if $i == 0}
					    <tr>
					{/if}
					{if !isset($item.URL)}
					    <td>{$item.LABEL}</td>
					{else}
					    <td class="bg-light">
						<a{if isset($item.NB_IMAGES)} title="{$item.NB_IMAGES|translate_dec:'%d photo':'%d photos'}"{/if} href="{$item.URL}">{$item.LABEL}</a>
					    </td>
					{/if}
					{if $i == 6}{assign var=i value=0}
					    </tr>
					{else}
					    {assign var=i value=$i+1}
					{/if}
				    {/foreach}
				</tbody>
			    </table>
			{/if}
		    </div>
		{/if}
		{if isset($bar.next)}
		    <a id="calendar-next" class="btn btn-secondary pull-right" href="{$bar.next.URL}">
			{$bar.next.LABEL}
			<i class="fa fa-arrow-right"></i>
		    </a>
		{/if}
	    </div>
	{/foreach}
    {/if}

    {if !empty($chronology_calendar.calendar_bars)}
	{foreach $chronology_calendar.calendar_bars as $bar}
	    <div class="card">
		<div class="card-header">
		    <a href="{$bar.U_HEAD}">{$bar.HEAD_LABEL}</a>
		    <span class="badge badge-secondary">{$bar.NB_IMAGES}</span>
		</div>
		<div class="card-body">
		    <ul class="calendar-month-list">
			{foreach $bar.items as $item}
  			    <li>
				<a href="{if isset($item.URL)}{$item.URL}{else}#{/if}">
				    {$item.LABEL}{if isset($item.NB_IMAGES)}
				    <span class="badge badge-secondary">{$item.NB_IMAGES}</span>{/if}
				</a>
			    </li>
			{/foreach}
		    </ul>
		</div>
	    </div>
	{/foreach}
    {/if}

    {if isset($chronology_calendar.month_view)}
	<div id="calendar-table" class="table-responsive">
	    <table id="calMonth" class="table table-sm table-bordered">
		<thead>
		    <tr>
			{foreach $chronology_calendar.month_view.wday_labels as $wday}
			    <th>{$wday}</th>
			{/foreach}
		    </tr>
		</thead>
		{foreach $chronology_calendar.month_view.weeks as $week}
		    <tr>
			{foreach $week as $day}
			    {if !empty($day)}
				{if isset($day.IMAGE)}
				    <td class="calDayCellFull">
					<div class="calDate">{$day.DAY}</div>
					<div class="calImg">
					    <a href="{$day.U_IMG_LINK}">
						<img class="img-fluid" src="{$day.IMAGE}" alt="{$day.IMAGE_ALT}" title="{$day.NB_ELEMENTS|translate_dec:'%d photo':'%d photos'}">
					    </a>
					</div>
				{else}
					<td class="calDayCellEmpty">{$day.DAY}
				{/if}
			    {else}{*blank cell first or last row only*}
				<td>
			    {/if}
				</td>
			{/foreach}{*day in week*}
		    </tr>
		{/foreach}{*week in month*}
	    </table>
	</div>
    {/if}

    {if !empty($thumbnails)}
	<div id="content" class="{if $category_view == 'list'}content-list{else}content-grid{/if}">
	    {include file="_thumbnails.tpl"}
	</div>
    {/if}
{/block}