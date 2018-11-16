{extends file="__layout.tpl"}

{block name="head_assets" append}
    <script src="./theme/js/install.js"></script>
{/block}

{block name="header"}{/block}
{block name="footer"}{/block}

{block name="breadcrumb"}
    <h2>Phyxo {'Version'|translate} {$RELEASE} - {'Installation'|translate}</h2>
{/block}

{block name="content"}
    {if $STEP === 'language'}
	<form method="POST" action="{$F_ACTION}" name="install_form" id="install-form">
	    <p>
		<label for="language">{'Default gallery language'|translate}</label>
		<select class="custom-select" id="language" name="language">
		    {html_options options=$LANGUAGES selected=$LANGUAGE}
		</select>
	    </p>
	    <p>
		<input class="btn btn-submit" type="submit" name="define_language" value="{'Save and continue'|translate}">
	    </p>
	</form>
    {/if}

    {if $STEP === 'check'}
	<form method="POST" action="{$F_ACTION}" name="install_form" id="install-form">
	    <div class="fieldset">
		<h3>{'Check directories permissions'|translate}</h3>

		<p>{'The installation process requires for the following directories at least read and write access'|translate}</p>
		<ul>
		    {assign var="success" value="fa fa-check text-success"}
		    {assign var="fail" value="fa fa-times text-danger"}
		    {foreach $READ_DIRECTORIES as $key => $directory}
			<li class="row">
			    <div class="col">
				{if empty($directory.path)}
				    {$ROOT}/{$key}
				{else}
				    {$directory.path}:
				{/if}
			    </div>
			    {if empty($directory.path)}
				<div class="col text-danger">
				    {'Directory does not exist'|translate}
				</div>
			    {else}
				<div class="col">
				    <i class="{if ($directory.readable)}{$success}{else}{$fail}{/if}"></i> {'readable'|translate}
				</div>
				<div class="col">
				    <i class="{if ($directory.writable)}{$success}{else}{$fail}{/if}"></i> {'writable'|translate}
				</div>
			    {/if}
			</li>
		    {/foreach}
		</ul>

		<p>{'The installation process requires for the following directories read and write access'|translate}</p>
		<ul>
		    {assign var="success" value="fa fa-check text-success"}
		    {assign var="fail" value="fa fa-times text-danger"}
		    {foreach $WRITE_DIRECTORIES as $key => $directory}
			<li class="row">
			    <div class="col">
				{if empty($directory.path)}
				    {$ROOT}/{$key}
				{else}
				    {$directory.path}:
				{/if}
			    </div>
			    {if empty($directory.path)}
				<div class="col text-danger">
				    {'Directory does not exist'|translate}
				</div>
			    {else}
				<div class="col">
				    <i class="{if ($directory.readable)}{$success}{else}{$fail}{/if}"></i> {'readable'|translate}
				</div>
				<div class="col">
				    <i class="{if ($directory.writable)}{$success}{else}{$fail}{/if}"></i> {'writable'|translate}
				</div>
			    {/if}
			</li>
		    {/foreach}
		</ul>

		<p>
		    {'After check permissions'|translate}, <a class="btn btn-sm btn-success" href="{$F_ACTION}">{'retry'|translate}</a></p>
		</p>
	    </div>
	    <p>
		<input class="btn btn-submit" type="submit" name="check_permissions" value="{'Save and continue'|translate}">
	    </p>
	</form>
    {/if}

    {if $STEP === 'database'}
	<form method="POST" action="{$F_ACTION}" name="install_form" id="install-form">
	    <div class="fieldset">
		<h3>{'Database configuration'|translate}</h3>
		<p>
		    {if count($F_DB_ENGINES)>1}
			<label for="dblayer">{'Database type'|translate}</label>
			<select name="dblayer" id="dblayer" class="custom-select is-valid">
			    {html_options options=$F_DB_ENGINES selected=$F_DB_LAYER}
			</select>
		    {else}
			<input type="hidden" name="dbengine" value="{$F_DB_LAYER}">
		    {/if}
		</p>
		<p class="no-sqlite">
		    <label for="dbhost">{'Host'|translate}</label>
		    <input class="form-control{if empty($F_DB_HOST)} is-invalid{else} is-valid{/if}" type="text" id="dbhost" name="dbhost" value="{$F_DB_HOST}" required>
		    <small class="form-text text-muted">{'localhost or other, supplied by your host provider'|translate}</small>
		</p>
		<p class="no-sqlite">
		    <label for="dbuser">{'User'|translate}</label>
		    <input class="form-control{if empty($F_DB_USER)} is-invalid{else} is-valid{/if}" type="text" id="dbuser" name="dbuser" value="{$F_DB_USER}">
		    <small class="form-text text-muted">{'user login given by your host provider'|translate}</small>
		</p>
		<p class="no-sqlite">
		    <label for="dbpasswd">{'Password'|translate}</label>
		    <input class="form-control{if $F_DB_PASSWORD_MISSING} is-invalid{/if}" type="password" name="dbpasswd" id="dbpasswd" value="">
		    <small class="form-text text-muted">{'user password given by your host provider'|translate}</small>
		</p>
		<p>
		    <label for="dbname">{'Database name'|translate}</label>
		    <input class="form-control{if empty($F_DB_NAME)} is-invalid{/if}" type="text" id="dbname" name="dbname" value="{$F_DB_NAME}" required>
		    <small class="form-text text-muted">{'also given by your host provider'|translate}</small>
		</p>
		<p>
		    <label for="prefix">{'Database table prefix'|translate}</label>
		    <input class="form-control" type="text" id="prefix" name="prefix" value="{$F_DB_PREFIX}">
		    <small class="form-text text-muted">{'database tables names will be prefixed with it (enables you to manage better your tables)'|translate}</small>
		</p>
	    </div>
	    <p>
		<input class="btn btn-submit" type="submit" name="install_database" value="{'Save and continue'|translate}">
	    </p>
	</form>
    {/if}

    {if $STEP === 'user'}
	<form method="POST" action="{$F_ACTION}" name="install_form" id="install-form">
	    <div class="fieldset">
		<h3>{'Create first user'|translate}</h3>
		<p>
		    <label for="admin_name">{'Username'|translate}</label>
		    <input class="form-control" type="text" id="admin_name" name="admin_name" value="{$F_ADMIN}">
		    <small class="form-text text-muted">{'It will be shown to the visitors. It is necessary for website administration'|translate}</small>
		</p>
		<p>
		    <label for="admin_pass1">{'Password'|translate}</label>
		    <input class="form-control" type="password" id="admin_pass1" name="admin_pass1" value="">
		    <small class="form-text text-muted">{'Keep it confidential, it enables you to access administration panel'|translate}</small>
		</p>
		<p>
		    <label for="admin_pass2">{'Password [confirm]'|translate}</label>
		    <input class="form-control" type="password" id="admin_pass2" name="admin_pass2" value="">
		    <small class="form-text text-muted">{'verification'|translate}</small>
		</p>
		<p>
		    <label for="admin_mail">{'Email address'|translate}</label>
		    <input class="form-control" type="text" name="admin_mail" id="admin_mail" value="{$F_ADMIN_EMAIL}">
		    <small class="form-text text-muted">{'Visitors will be able to contact site administrator with this mail'|translate}</small>
		</p>
	    </div>
	    <p>
		<input class="btn btn-submit" type="submit" name="create_user" value="{'Save and continue'|translate}">
	    </p>
	</form>
    {/if}

    {if $STEP === 'success'}
	<p>
	    <a class="btn btn-success" href="../">{'Visit Gallery'|translate}</a>
	</p>
    {/if}
{/block}

{block name="aside"}
    <aside id="sidebar" role="navigation" class="install">
	<h3>{'Installation steps'|translate}</h3>
	<ul class="list-group">
	    {foreach $STEPS as $step_key => $step}
		<li class="list-group-item{if $step_key === $STEP} active{elseif $step.done} disabled{/if}">{$step.label|translate}</li>
	    {/foreach}
	</ul>
	<script>var menuitem_active = "{$STEP}";</script>
    </aside>
{/block}
