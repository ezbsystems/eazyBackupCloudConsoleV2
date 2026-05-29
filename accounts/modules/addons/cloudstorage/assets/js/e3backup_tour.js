/*
 * e3 Cloud Backup - guided onboarding tours.
 *
 * Wraps the vendored driver.js IIFE build (window.driver.js.driver) and
 * exposes a small ebE3Tour API used by:
 *   - templates/e3backup_getting_started.tpl    (auto-start on first visit)
 *   - templates/partials/e3backup_shell.tpl     (persistent "Setup progress" pill + auto-start of the second tour)
 *
 * Two distinct tours live in this file:
 *
 *  1. The "Welcome" tour (start / destroy / maybeAutoStart): the original 6-step
 *     overview that fires on first visit to the Getting Started page.
 *
 *  2. The "First Job" tour (startFirstJobTour / destroyFirstJobTour /
 *     maybeAutoStartFirstJobTour): a second-pass tour that fires once the
 *     customer has installed an agent (step 1+2 complete) but has not yet
 *     created their first backup job (step 3). It walks the customer from
 *     the Users list, through the User Detail page, into the Create Job
 *     dropdown and the local-agent wizard. Anchors are filtered to match
 *     the current page, so this same controller does the right thing on
 *     both `view=users` and `view=user_detail`.
 *
 * All steps are anchored via data-tour="..." attributes. Step elements that
 * are missing on the current page are quietly skipped.
 */
(function (window, document) {
    'use strict';

    function getDriverCtor() {
        if (window.driver && window.driver.js && typeof window.driver.js.driver === 'function') {
            return window.driver.js.driver;
        }
        return null;
    }

    var GETTING_STARTED_URL = 'index.php?m=cloudstorage&page=e3backup&view=getting_started';

    function paramFromQuery(name) {
        try {
            var match = new RegExp('[?&]' + name + '=([^&]*)').exec(location.search);
            return match ? decodeURIComponent(match[1]) : '';
        } catch (e) {
            return '';
        }
    }

    function currentView() {
        return paramFromQuery('view');
    }

    function isOnGettingStarted() {
        try {
            return /[?&]m=cloudstorage(?=[&]|$)/.test(location.search)
                && /[?&]page=e3backup(?=[&]|$)/.test(location.search)
                && currentView() === 'getting_started';
        } catch (e) {
            return false;
        }
    }

    function isOnUsers() {
        return currentView() === 'users';
    }

    function isOnUserDetail() {
        return currentView() === 'user_detail';
    }

    function recordEvent(event) {
        if (typeof window.ebE3RecordOnboardingEvent === 'function') {
            return window.ebE3RecordOnboardingEvent(event);
        }
        try {
            return fetch('modules/addons/cloudstorage/api/e3backup_onboarding_event.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'event=' + encodeURIComponent(event)
            });
        } catch (e) {
            return Promise.resolve();
        }
    }

    // ---------------------------------------------------------------------
    // Welcome tour (original)
    // ---------------------------------------------------------------------

    function welcomeSteps() {
        return [
            {
                element: '[data-tour="sidebar-getting-started"]',
                popover: {
                    title: 'Your home base',
                    description: 'Come back to Getting Started any time. We refresh this page automatically as you progress.'
                }
            },
            {
                element: '[data-tour="sidebar-download"]',
                popover: {
                    title: 'Get the installer here',
                    description: 'Click Download Agent any time you need the installer for a new computer.'
                }
            },
            {
                element: '[data-tour="gs-step-download"]',
                popover: {
                    title: 'Step 1 - Download',
                    description: 'Download the installer onto the computer you want to back up, we support Windows or Linux.'
                }
            },
            {
                element: '[data-tour="gs-step-agent-online"]',
                popover: {
                    title: 'Step 2 - Sign in from the agent',
                    description: 'After running the installer, sign in with your account email and the password you just set to enroll the agent.'
                }
            },
            {
                element: '[data-tour="gs-step-first-job"]',
                popover: {
                    title: 'Step 3 - Create a backup',
                    description: 'Once your agent is enrolled and online, navigate to the Users Page to select what to back up: files, folders, disks, or virtual machines.'
                }
            },
            {
                element: '[data-tour="gs-step-first-run"]',
                popover: {
                    title: 'Step 4 - Run it',
                    description: 'Run the backup once (or wait for the schedule). We mark this complete when your first run succeeds.'
                }
            }
        ];
    }

    function availableSteps(allSteps) {
        return allSteps.filter(function (step) {
            try {
                return document.querySelector(step.element) !== null;
            } catch (e) {
                return false;
            }
        });
    }

    var currentDriver = null;

    function buildWelcomeDriver() {
        var driverCtor = getDriverCtor();
        if (!driverCtor) return null;
        var steps = availableSteps(welcomeSteps());
        if (steps.length === 0) return null;
        return driverCtor({
            showProgress: true,
            allowClose: true,
            overlayOpacity: 0.5,
            stagePadding: 6,
            stageRadius: 8,
            popoverClass: 'eb-e3-tour-popover',
            steps: steps,
            onCloseClick: function () {
                recordEvent('tour_dismissed');
                if (currentDriver && typeof currentDriver.destroy === 'function') {
                    currentDriver.destroy();
                }
            },
            onDestroyStarted: function () {
                if (currentDriver) {
                    try {
                        if (!currentDriver.hasNextStep()) {
                            recordEvent('tour_completed');
                        }
                    } catch (e) {}
                    currentDriver.destroy();
                }
            }
        });
    }

    // ---------------------------------------------------------------------
    // First Job tour (new)
    //
    // Built dynamically per-page so the same controller produces the right
    // step list for view=users (1 step) vs view=user_detail (5 steps).
    // Steps 1-2 of the user_detail variant don't show Next buttons: the
    // tour advances via click listeners on the highlighted element.
    // ---------------------------------------------------------------------

    var firstJobDriver = null;
    var firstJobAdvanceCleanup = null;
    var firstJobModalObserver = null;

    function firstJobUsersSteps() {
        return [{
            element: '[data-tour="users-row"]',
            popover: {
                title: 'Open your user',
                description: 'Click your username to open the user detail page. That is where you create your first backup job.',
                showButtons: ['close']
            }
        }];
    }

    function firstJobUserDetailSteps() {
        return [
            {
                element: '[data-tour="user-detail-create-job-btn"]',
                popover: {
                    title: 'Create your first job',
                    description: 'Click Create Job to open the backup type menu.',
                    showButtons: ['close']
                }
            },
            {
                element: '[data-tour="user-detail-create-job-local"]',
                popover: {
                    title: 'Choose e3 Cloud Backup',
                    description: 'Pick e3 Cloud Backup to back up files, folders, disk images, or virtual machines from a Windows or Linux agent.',
                    showButtons: ['close']
                }
            },
            {
                element: '[data-tour="local-wizard-name"]',
                popover: {
                    title: 'Name your job',
                    description: 'Give the job a name you will recognise later, for example "Office PC - Documents". This is the first required field — the Next button stays disabled until it has a value.',
                    // Hide "Previous" on the first wizard step: the two
                    // earlier steps (Create Job button, e3 Cloud Backup
                    // menu option) are no longer mounted in the DOM once
                    // the wizard is open, so going back would render the
                    // popover at 0,0 with no anchor.
                    showButtons: ['next', 'close']
                },
                onHighlighted: function () {
                    // The wizard body sometimes mounts with the customer's
                    // scroll already part-way down. Ensure the Name field
                    // is in view and focused so the tour highlight lands on
                    // a visible, ready-to-type field.
                    try {
                        var body = document.querySelector('#localJobWizardModal .eb-modal-body');
                        if (body) body.scrollTop = 0;
                        var nameInput = document.getElementById('localWizardName');
                        if (nameInput && typeof nameInput.focus === 'function') {
                            setTimeout(function () { try { nameInput.focus({ preventScroll: false }); } catch (_) { nameInput.focus(); } }, 80);
                        }
                    } catch (_) {}
                }
            },
            {
                element: '[data-tour="local-wizard-engine"]',
                popover: {
                    title: 'Pick what to back up',
                    description: 'File Backup (Archive) is the right starting point for most folders. Disk Image protects a whole drive; Hyper-V protects virtual machines.',
                    showButtons: ['previous', 'next', 'close']
                }
            },
            {
                element: '[data-tour="local-wizard-agent"]',
                popover: {
                    title: 'Select the agent',
                    description: 'Choose the computer that has the data. After this, walk through Source, Schedule, Policy, and Review at your own pace, then click Create.',
                    showButtons: ['previous', 'next', 'close']
                }
            }
        ];
    }

    function cleanupFirstJobAdvanceHooks() {
        if (typeof firstJobAdvanceCleanup === 'function') {
            try { firstJobAdvanceCleanup(); } catch (e) {}
            firstJobAdvanceCleanup = null;
        }
        if (firstJobModalObserver && typeof firstJobModalObserver.disconnect === 'function') {
            try { firstJobModalObserver.disconnect(); } catch (e) {}
            firstJobModalObserver = null;
        }
    }

    function destroyFirstJobDriver() {
        cleanupFirstJobAdvanceHooks();
        if (firstJobDriver && typeof firstJobDriver.destroy === 'function') {
            try { firstJobDriver.destroy(); } catch (e) {}
        }
        firstJobDriver = null;
    }

    // Wires up click listeners that advance the driver when the customer
    // performs the expected action for steps 1 and 2 of the user_detail
    // variant. Cleared automatically when the tour is destroyed.
    function installFirstJobAdvanceHooks() {
        cleanupFirstJobAdvanceHooks();

        // Single-shot guard for the wizard-open advance. Without this the
        // MutationObserver fires first (advancing step 2 -> 3), then the
        // 250ms safety timeout fires and advances AGAIN (step 3 -> 4),
        // which makes the tour land on "Pick what to back up" instead of
        // "Name your job".
        var advancedToWizardName = false;

        var advanceOnceToWizardName = function () {
            if (advancedToWizardName || !firstJobDriver) return;
            advancedToWizardName = true;
            try {
                if (firstJobModalObserver) {
                    firstJobModalObserver.disconnect();
                    firstJobModalObserver = null;
                }
            } catch (e) {}
            try { firstJobDriver.moveNext(); } catch (e) {}
        };

        var onCreateJobClick = function (event) {
            var btn = event.target && event.target.closest && event.target.closest('[data-tour="user-detail-create-job-btn"]');
            if (!btn || !firstJobDriver) return;
            // The menu opens via Alpine on the same click - give it a tick to render.
            setTimeout(function () {
                try { if (firstJobDriver && firstJobDriver.getActiveIndex && firstJobDriver.getActiveIndex() === 0) firstJobDriver.moveNext(); } catch (e) {}
            }, 60);
        };

        var onLocalOptionClick = function (event) {
            var opt = event.target && event.target.closest && event.target.closest('[data-tour="user-detail-create-job-local"]');
            if (!opt || !firstJobDriver) return;
            advancedToWizardName = false;
            // The wizard mounts via JS on click. Advance once its first
            // anchor element appears in the DOM.
            firstJobModalObserver = new MutationObserver(function () {
                if (document.querySelector('[data-tour="local-wizard-name"]')) {
                    advanceOnceToWizardName();
                }
            });
            try { firstJobModalObserver.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style'] }); } catch (e) {}
            // Safety: try a direct moveNext after a short delay in case the
            // modal was already in the DOM (e.g. hidden vs unmounted). The
            // single-shot guard above prevents the double-advance when both
            // paths fire.
            setTimeout(function () {
                if (document.querySelector('[data-tour="local-wizard-name"]')) {
                    advanceOnceToWizardName();
                }
            }, 250);
        };

        document.addEventListener('click', onCreateJobClick, true);
        document.addEventListener('click', onLocalOptionClick, true);
        firstJobAdvanceCleanup = function () {
            document.removeEventListener('click', onCreateJobClick, true);
            document.removeEventListener('click', onLocalOptionClick, true);
        };
    }

    function buildFirstJobDriver() {
        var driverCtor = getDriverCtor();
        if (!driverCtor) return null;
        var allSteps;
        if (isOnUsers()) {
            allSteps = firstJobUsersSteps();
        } else if (isOnUserDetail()) {
            allSteps = firstJobUserDetailSteps();
        } else {
            return null;
        }
        var steps = availableSteps(allSteps);
        if (steps.length === 0) return null;
        return driverCtor({
            showProgress: steps.length > 1,
            allowClose: true,
            overlayOpacity: 0.45,
            stagePadding: 6,
            stageRadius: 8,
            popoverClass: 'eb-e3-tour-popover',
            steps: steps,
            onCloseClick: function () {
                recordEvent('first_job_tour_dismissed');
                destroyFirstJobDriver();
            },
            onDestroyStarted: function () {
                if (firstJobDriver) {
                    try {
                        if (!firstJobDriver.hasNextStep()) {
                            recordEvent('first_job_tour_completed');
                        }
                    } catch (e) {}
                }
                destroyFirstJobDriver();
            }
        });
    }

    // ---------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------

    var ebE3Tour = {
        // ----- Welcome tour (original) -----
        start: function () {
            currentDriver = buildWelcomeDriver();
            if (!currentDriver) {
                if (!isOnGettingStarted()) {
                    location.href = GETTING_STARTED_URL;
                }
                return;
            }
            recordEvent('tour_started');
            currentDriver.drive();
        },
        destroy: function () {
            if (currentDriver && typeof currentDriver.destroy === 'function') {
                currentDriver.destroy();
                currentDriver = null;
            }
        },
        maybeAutoStart: function (state) {
            if (!isOnGettingStarted()) return;
            if (!state || state.tour_completed || state.tour_dismissed) return;
            if (state.all_complete) return;
            if (state.tour_started) return;
            ebE3Tour.start();
        },

        // ----- First-Job tour (new) -----
        startFirstJobTour: function () {
            destroyFirstJobDriver();
            firstJobDriver = buildFirstJobDriver();
            if (!firstJobDriver) return;
            if (isOnUserDetail()) {
                installFirstJobAdvanceHooks();
            }
            recordEvent('first_job_tour_started');
            firstJobDriver.drive();
        },
        destroyFirstJobTour: function () {
            destroyFirstJobDriver();
        },
        // Gate: agent installed (steps 1+2) but no job yet (step 3),
        // and the customer hasn't already started, finished, or dismissed
        // this second tour.
        maybeAutoStartFirstJobTour: function (state) {
            if (!state) return;
            if (!isOnUsers() && !isOnUserDetail()) return;
            var steps = state.steps || {};
            var downloadDone = steps.download && steps.download.complete;
            var agentDone    = steps.agent_online && steps.agent_online.complete;
            var firstJobDone = steps.first_job && steps.first_job.complete;
            if (!downloadDone || !agentDone) return;
            if (firstJobDone) return;
            if (state.first_job_tour_completed || state.first_job_tour_dismissed) return;
            // Allow the User Detail variant to fire even after the Users-page
            // variant has set first_job_tour_started, so the customer who
            // followed the prompt to click their username keeps getting
            // guided. We just skip re-firing on the Users page once we've
            // already shown it.
            if (isOnUsers() && state.first_job_tour_started) return;
            // Anchors on the Users list and the user_detail header are
            // mounted async (Alpine init + fetch). Poll briefly for the
            // first anchor to appear before starting; bail after ~10s so
            // we never sit indefinitely.
            var selector = isOnUsers()
                ? '[data-tour="users-row"]'
                : '[data-tour="user-detail-create-job-btn"]';
            var attempts = 0;
            var poll = function () {
                attempts++;
                if (document.querySelector(selector)) {
                    ebE3Tour.startFirstJobTour();
                    return;
                }
                if (attempts < 40) setTimeout(poll, 250);
            };
            setTimeout(poll, 250);
        }
    };

    window.ebE3Tour = ebE3Tour;

    // Convenience: when the Setup progress pill in the app header is clicked
    // and the customer is NOT on Getting Started, browser-navigate there;
    // when they ARE on Getting Started, re-launch the tour.
    document.addEventListener('click', function (event) {
        var target = event.target;
        while (target && target !== document) {
            if (target.matches && target.matches('[data-action="eb-e3-replay-tour"]')) {
                event.preventDefault();
                if (isOnGettingStarted()) {
                    ebE3Tour.start();
                } else {
                    location.href = GETTING_STARTED_URL;
                }
                return;
            }
            target = target.parentNode;
        }
    });
})(window, document);
