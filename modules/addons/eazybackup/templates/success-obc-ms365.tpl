<div class="bg-gray-800 text-gray-300 min-h-screen p-4">
  <!-- Page Header -->
  <header class="mb-6">

  </header>

  <!-- Main Content Container -->
  <main class="bg-gray-700 p-6 rounded-lg max-w-4xl mx-auto">
    <h1 class="text-2xl font-semibold text-center">
      Your account has been created successfully
    </h1>
    <!-- Instruction Section -->
    <section class="border-b border-gray-500 pb-4 mb-6">
      <p class="font-semibold mb-2">Next Steps:</p>
        <div class="mb-4">
          <p class="font-bold">1. Log In to the Control Panel</p>
          <p>
            <span>Control Panel:</span>
            <a class="underline text-blue-400 hover:text-blue-300" href="https://panel.obcbackup.com/">
                https://panel.obcbackup.com/
            </a>
          </p>
          <p>
            <span>Username:</span> {$username}
          </p>
          <p>
            <span>Password:</span> (Use the password you selected during sign-up)
          </p>
        </div>
        <div class="mb-4">
          <p class="font-bold">2. Configure Your Backup</p>
          <p>
            Select <span class="italic">Protected Items</span> &rarr; 'Add new Protected Item' to set up and customize your MS 365 backup.
          </p>
        </div>
          <p>
            Watch the step-by-step configuration video below. As always, if you need further assistance, our support team is ready to help.
          </p>
        
      
    </section>

    <!-- Video Section -->
    <section>
      <video class="w-full h-auto rounded" controls>
        <source src="https://eazybackup.com/wp-content/uploads/2024/05/MS365_Backup_Getting_Started.mp4" type="video/mp4">
        Your browser does not support the video tag.
      </video>
    </section>
  </main>

  <!-- JavaScript for Login Form Handling -->
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
</div>
