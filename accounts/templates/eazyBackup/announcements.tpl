{capture name=ebAnnouncementsBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php" class="eb-breadcrumb-link">Client Area</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">{lang key='announcementstitle'}</span>
    </div>
{/capture}

{capture name=ebAnnouncementsContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebAnnouncementsBreadcrumb
        ebPageTitle={lang key='announcementstitle'}
        ebPageDescription="Read product, maintenance, and service announcements published for client accounts."
    }

    <div class="eb-content-list">
        {foreach $announcements as $announcement}
            <div class="eb-content-card">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="eb-content-title">
                        <a href="{routePath('announcement-view', $announcement.id, $announcement.urlfriendlytitle)}" class="eb-link">{$announcement.title}</a>
                    </h2>
                    {if $announcement.editLink}
                        <a href="{$announcement.editLink}" class="eb-btn eb-btn-secondary eb-btn-xs show-on-hover">
                            <i class="fas fa-pencil-alt fa-fw"></i>
                            <span>{lang key='edit'}</span>
                        </a>
                    {/if}
                </div>

                <div class="eb-content-meta">
                    <span><i class="far fa-calendar-alt fa-fw"></i> {$carbon->createFromTimestamp($announcement.timestamp)->format('jS F Y')}</span>
                </div>

                <article class="eb-content-body">
                    {if $announcement.text|strip_tags|strlen < 350}
                        {$announcement.text}
                    {else}
                        {$announcement.summary}
                    {/if}
                </article>

                <div class="mt-4">
                    <a href="{routePath('announcement-view', $announcement.id, $announcement.urlfriendlytitle)}" class="eb-btn eb-btn-secondary eb-btn-sm">
                        <span>{lang key='announcementscontinue'}</span>
                        <i class="far fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        {foreachelse}
            {include file="$template/includes/alert-darkmode.tpl" type="info" msg="{lang key='noannouncements'}" textcenter=true}
        {/foreach}
    </div>

    {if $prevpage || $nextpage}
        <div class="mt-6 eb-table-pagination">
            <div></div>
            <div class="flex flex-wrap items-center gap-2">
                {foreach $pagination as $item}
                    <a class="eb-table-pagination-button{if $item.disabled} opacity-50 pointer-events-none{/if}{if $item.active} is-active{/if}" href="{$item.link}">{$item.text}</a>
                {/foreach}
            </div>
        </div>
    {/if}

    {if $announcementsFbRecommend}
        <script>
            (function(d, s, id) {
                var js, fjs = d.getElementsByTagName(s)[0];
                if (d.getElementById(id)) {
                    return;
                }
                js = d.createElement(s); js.id = id;
                js.src = "//connect.facebook.net/{lang key='locale'}/all.js#xfbml=1";
                fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'facebook-jssdk'));
        </script>
    {/if}
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageContent=$smarty.capture.ebAnnouncementsContent
}
