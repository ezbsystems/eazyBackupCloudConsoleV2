{capture name=ebDownloadsBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php" class="eb-breadcrumb-link">Client Area</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">{lang key='downloads'}</span>
    </div>
{/capture}

{capture name=ebDownloadsContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebDownloadsBreadcrumb
        ebPageTitle={lang key='downloads'}
        ebPageDescription="Search the download library, browse categories, and access the most requested files."
    }

    <div class="eb-subpanel mb-4">
        <form role="form" method="post" action="{routePath('download-search')}" class="eb-search-row">
            <input type="text" name="search" id="inputDownloadsSearch" class="eb-input" placeholder="{lang key='downloadssearch'}" />
            <button type="submit" id="btnDownloadsSearch" class="eb-btn eb-btn-primary">
                {lang key='search'}
            </button>
        </form>
    </div>

    {if $dlcats}
        <div class="grid gap-4 xl:grid-cols-2">
            {foreach $dlcats as $category}
                <a href="{routePath('download-by-cat', {$category.id}, {$category.urlfriendlyname})}" class="eb-content-card">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="eb-content-title">
                                <i class="fal fa-folder fa-fw"></i>
                                {$category.name}
                            </h2>
                            <p class="eb-content-body mt-2">{$category.description}</p>
                        </div>
                        <span class="eb-badge eb-badge--info">
                            {lang key="downloads.numDownload{if $kbcat.numarticles != 1}s{/if}" num=$category.numarticles}
                        </span>
                    </div>
                </a>
            {/foreach}
        </div>
    {else}
        {include file="$template/includes/alert-darkmode.tpl" type="info" msg="{lang key='downloadsnone'}" textcenter=true}
    {/if}

    {if $mostdownloads}
        <div class="mt-4 eb-home-panel">
            <div class="eb-home-panel-header">
                <h3 class="eb-home-panel-title">
                    <i class="fal fa-star fa-fw"></i>
                    {lang key='downloadspopular'}
                </h3>
            </div>
            <div class="eb-list-stack">
                {foreach $mostdownloads as $download}
                    <a href="{$download.link}" class="eb-list-item">
                        <div class="min-w-0">
                            <div class="eb-table-primary">
                                {$download.type|replace:'alt':' class="pr-1" alt'}
                                {$download.title}
                            </div>
                            <div class="eb-choice-card-description mt-2">
                                {$download.description}
                                <br>
                                <strong>{lang key='downloadsfilesize'}: {$download.filesize}</strong>
                            </div>
                        </div>
                        {if $download.clientsonly}
                            <span class="eb-badge eb-badge--danger">
                                <i class="fas fa-lock fa-fw"></i>
                                {lang key='restricted'}
                            </span>
                        {/if}
                    </a>
                {/foreach}
            </div>
        </div>
    {/if}
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageContent=$smarty.capture.ebDownloadsContent
}
