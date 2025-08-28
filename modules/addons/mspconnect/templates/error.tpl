<div class="alert alert-danger">
    <h4><i class="fa fa-exclamation-triangle"></i> {$title|default:"Error"}</h4>
    <p>{$error}</p>
    {if $debug}
        <hr>
        <strong>Debug Information:</strong>
        <pre style="font-size: 11px; max-height: 300px; overflow-y: auto;">{$debug}</pre>
    {/if}
</div> 