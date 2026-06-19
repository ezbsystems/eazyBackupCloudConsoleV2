<div class="eb-job-type-icon" aria-hidden="true">
    <template x-if="isMs365Job(job)">
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_brand_icon.tpl" ebBrandIconClass='eb-brand-icon eb-brand-icon--job-card'}
    </template>
    <template x-if="!isMs365Job(job) && (job.source_type || '').toLowerCase() === 'local_agent'">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/>
        </svg>
    </template>
    <template x-if="!isMs365Job(job) && (job.source_type || '').toLowerCase() !== 'local_agent'">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"/>
        </svg>
    </template>
</div>
