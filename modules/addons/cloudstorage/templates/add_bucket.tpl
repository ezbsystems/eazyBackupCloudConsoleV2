<div class="heading-row">
    <h1>Create Bucket</h1>
</div>

<div class="container">
    <div class="bucket-row">
        <div class="options-card">
            <div class="input-title-row">
                <div class="bucket-title">
                    <form method="post">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="currentColor"
                            class="bi bi-bucket-fill" viewBox="0 0 16 16">
                            <path
                                d="M2.522 5H2a.5.5 0 0 0-.494.574l1.372 9.149A1.5 1.5 0 0 0 4.36 16h7.278a1.5 1.5 0 0 0 1.483-1.277l1.373-9.149A.5.5 0 0 0 14 5h-.522A5.5 5.5 0 0 0 2.522 5zm1.005 0a4.5 4.5 0 0 1 8.945 0H3.527z" />
                        </svg>
                        <span class="bucket-heading-text">Create Bucket</span>
                        <div class="bucket-options">
                            <div class="bucket-options-text">Bucket Name</div>
                                <input type="hidden" name="uid" value="">
                                <input class="title-input" id="bucketName" type="text" name="bucketName" placeholder="Enter bucket name" required>
                                <div class="alert alert-danger" id="error-message">
                                    {* error message here *}
                                </div>
                            </div>
                            <div class="toggle-buttons bucket-options">
                                <div class="toggle-button bucket-options-text">
                                    <label for="versioning-toggle">Versioning:</label>
                                    <label class="switch">
                                        <input type="checkbox" id="versioning-toggle" name="versioning">
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="toggle-buttons bucket-options">
                                <div class="toggle-button bucket-options-text">
                                    <label for="object-lock-toggle">Object Locking:</label>
                                    <label class="switch">
                                        <input type="checkbox" id="object-lock-toggle" name="objectLock">
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="bucket-heading-text bucket-title-btns">
                                <a href="" style="text-decoration: none;">
                                    <button class="bucket-mgr-btn-primary" type="submit">
                                        <span class="bucket-mgr-btn-text">Create Bucket</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                            class="bi bi-cloud-plus" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd"
                                                d="M8 5.5a.5.5 0 0 1 .5.5v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 .5-.5z" />
                                            <path
                                                d="M4.406 3.342A5.53 5.53 0 0 1 8 2c2.69 0 4.923 2 5.166 4.579C14.758 6.804 16 8.137 16 9.773 16 11.569 14.502 13 12.687 13H3.781C1.708 13 0 11.366 0 9.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383zm.653.757c-.757.653-1.153 1.44-1.153 2.056v.448l-.445.049C2.064 6.805 1 7.952 1 9.318 1 10.785 2.23 12 3.781 12h8.906C13.98 12 15 10.988 15 9.773c0-1.216-1.02-2.228-2.313-2.228h-.5v-.5C12.188 4.825 10.328 3 8 3a4.53 4.53 0 0 0-2.941 1.1z" />
                                        </svg>
                                    </button>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{literal}
<script>
    document.getElementById('bucketName').addEventListener('input', function (e) {
        var bucketName = e.target.value;
        var isValid = /^(?!-)(?!.*--)(?!.*\.\.)(?!.*\.-)(?!.*-\.)[a-z0-9-.]*[a-z0-9]$/.test(bucketName) && !(/^\.|\.$/.test(bucketName));
        var errorMessageDiv = document.getElementById('error-message');

        if (bucketName.length < 3 || bucketName.length > 63) {
            errorMessageDiv.textContent = 'Bucket names must be between 3 and 63 characters long.';
        } else if (!isValid) {
            errorMessageDiv.textContent = 'Bucket names can only contain lowercase letters, numbers, and hyphens, and must not start or end with a hyphen or period, or contain two consecutive periods or period-hyphen(-) or hyphen-period(-.).';
        } else {
            errorMessageDiv.textContent = '';
        }
    });
</script>
{/literal}