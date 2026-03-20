{foreach $customfields as $customfield}
    <div class="space-y-2">
        <label for="customfield{$customfield.id}" class="eb-field-label">{$customfield.name}</label>
        {$customfield.input}
        {if $customfield.description}
            <p class="eb-field-help">{$customfield.description}</p>
        {/if}
    </div>
{/foreach}
