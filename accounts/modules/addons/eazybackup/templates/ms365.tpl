<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-8 min-h-screen flex items-center">

        <div class="relative rounded-3xl border border-slate-800/80 bg-slate-900/80 backdrop-blur-md shadow-[0_18px_60px_rgba(0,0,0,0.6)] max-w-5xl mx-auto px-6 py-7 lg:px-8 lg:py-8">
            <!-- Success pill -->
            <div class="absolute -top-4 left-6 inline-flex items-center gap-2 rounded-full border border-emerald-500/40 bg-slate-950/90 px-3 py-1 text-xs text-emerald-400 shadow-lg">
              
                <span>Microsoft 365 backup account created</span>
            </div>

            <div class="flex flex-col gap-2 lg:flex-row lg:items-start">
                <!-- Left: welcome + steps -->
                <div class="w-full lg:w-1/2 space-y-6 lg:pr-6">
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[radial-gradient(circle_at_30%_0%,#fecc80_0%,#fe7800_35%,#fe5000_100%)] shadow-[0_0_40px_rgba(248,113,22,0.6)]">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-12">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl text-slate-50 font-semibold">
                                Your Microsoft 365 backup account is ready
                            </h1>
                            <p class="mt-1 text-sm text-slate-300">
                                Follow these quick steps to sign in and protect your Microsoft 365 data.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h2 class="text-sm font-semibold text-slate-200">Step 1 · Sign in to the Control Panel</h2>
                        <p class="text-sm text-slate-300">
                            Go to
                            <a class="text-sky-400 hover:text-sky-300 underline underline-offset-2" href="https://panel.eazybackup.ca/" target="_blank" rel="noopener noreferrer">
                                https://panel.eazybackup.ca/
                            </a>
                            and sign in with your new account.
                        </p>
                        <div class="text-xs rounded-2xl border border-slate-800 bg-slate-900/70 px-4 py-3 space-y-1.5">
                            <p class="text-slate-300">
                                <span class="font-semibold">Username:</span>
                                <span class="ml-1">{$username}</span>
                            </p>
                            <p class="text-slate-400">
                                <span class="font-semibold text-slate-200">Password:</span>
                                <span class="ml-1">Use the password you selected during sign-up.</span>
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h2 class="text-sm font-semibold text-slate-200">Step 2 · Configure your Microsoft 365 backup</h2>
                        <p class="text-sm text-slate-300">
                            In the Control Panel, open
                            <span class="font-semibold">Protected Items</span>
                            &rarr;
                            <span class="font-semibold">Add new Protected Item</span>
                            and choose the Microsoft 365 backup type to set up and customize your backup.
                        </p>
                        {* <p class="text-xs text-slate-400">
                            Not sure which options to choose? Start with user mailboxes and OneDrive. You can add SharePoint and Teams later.
                        </p> *}
                    </div>
                </div>

                <!-- Right: video guide -->
                <div class="w-full lg:w-1/2 lg:pl-6">
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/90 px-4 py-4 shadow-inner space-y-3">
                        <h3 class="text-sm font-medium text-slate-100">
                            Microsoft 365 backup video guide
                        </h3>
                        <p class="text-xs text-slate-400">
                            Watch this short walkthrough to see how to connect your tenant and start protecting mailboxes, OneDrive, SharePoint, and Teams.
                        </p>
                        <div class="aspect-video rounded-lg overflow-hidden border border-slate-800/80 bg-black">
                            <video class="w-full h-full" controls>
                                <source src="https://eazybackup.com/wp-content/uploads/2024/05/MS365_Backup_Getting_Started.mp4" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

  <!-- Footer Section -->
    <footer class="h-36 bg-gray-700 lg:border-t border-gray-700">
    </footer>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      var loginForm = document.getElementById("whmcsLoginForm");
      if (loginForm) {
        loginForm.addEventListener("submit", function() {
          var redirectTo = '/clientarea.php?action=details';
          var hiddenInput = document.createElement("input");
          hiddenInput.type = "hidden";
          hiddenInput.name = "goto";
          hiddenInput.value = redirectTo;
          loginForm.appendChild(hiddenInput);
        });
      }
    });
  </script>

