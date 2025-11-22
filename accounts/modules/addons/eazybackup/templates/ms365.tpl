<div class="min-h-screen bg-slate-800 text-gray-300">
  <div class="container mx-auto px-4 pb-8">

    <div class="flex flex-col sm:flex-row h-16 mx-12 justify-between items-start sm:items-center">
      <!-- Navigation Horizontal -->
      <div class="flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
          <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
        </svg>       
        <h2 class="text-2xl font-semibold text-white ml-2">Your account has been created successfully </h2>
      </div> 
    </div>   

    <!-- Content Container -->
    <div class="mx-8 space-y-12">
      <div class="min-h-[calc(100vh-14rem)] h-full p-6 xl:p-12 bg-[#11182759] space-y-4 rounded-xl border border-gray-700">        
          <div class="">               
            <h2 class="font-semibold text-gray-300">1. Log In to the Control Panel</h2>
              <p class="text-sm text-gray-300 ml-4">
                <span>Control Panel:</span>
                <a class="text-sky-500 hover:text-sky-400" href="https://panel.eazybackup.ca/">
                  https://panel.eazybackup.ca/
                </a>
              </p>
              <p class="text-sm text-gray-300 ml-4">
                <span>Username:</span> {$username}
              </p>
              <p class="text-sm text-gray-300 ml-4">
                <span>Password:</span> (Use the password you selected during sign-up)
              </p>  
          </div>

          <div class="">   
            <h2 class="font-semibold text-gray-300">2. Configure Your Backup</h2>
              <p class="text-sm text-gray-300 ml-4">
                Select <span class="italic">'Protected Items'</span> &rarr; 'Add new Protected Item' to set up and customize your MS 365 backup.
              </p>        
              <p class="text-sm text-gray-300 ml-4">
                Watch the step-by-step configuration video below. As always, if you need further assistance, our support team is ready to help.
              </p> 
          </div>
          <!-- Video Section -->
          <section>
            <video class="w-full max-w-2xl h-auto rounded-lg" controls>
              <source src="https://eazybackup.com/wp-content/uploads/2024/05/MS365_Backup_Getting_Started.mp4" type="video/mp4">
              Your browser does not support the video tag.
            </video>
          </section>
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

