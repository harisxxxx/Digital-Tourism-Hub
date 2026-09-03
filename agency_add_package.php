<?php
// 1. Set the page title
$page_title = 'Add New Package'; 

// 2. Includes
require_once 'includes/agency_header.php'; 
require_once 'config/db_connect.php'; 

// 3. --- NEW: MESSAGE HANDLING ---
$form_message = '';
if (isset($_SESSION['success_message'])) {
    $form_message = "<p class='message success'><i class='bi bi-check-circle-fill'></i> " . $_SESSION['success_message'] . "</p>";
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $form_message = "<p class='message error'><i class='bi bi-exclamation-triangle-fill'></i> " . $_SESSION['error_message'] . "</p>";
    unset($_SESSION['error_message']);
}
// --- END: MESSAGE HANDLING ---

// 4. Include the sidebar
require_once 'includes/agency_sidebar.php'; 
?>

<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-plus-circle-fill"></i> Add New Package</h1>
        <p>Fill out the details to create a new tour package.</p>
    </header>

    <div class="content-card">
        
        <?php echo $form_message; ?>

        <form id="packageForm" action="agency_add_package_action.php" method="POST" enctype="multipart/form-data" class="package-form" novalidate>
            
            <div class="form-group">
                <label for="title">Package Title *</label>
                <input type="text" id="title" name="title" placeholder="e.g., 10-Day Skardu & Hunza Expedition" required>
                <div class="field-error" aria-live="polite"></div>
            </div>

            <div class="form-group">
                <label for="package_image">Package Image *</label>
                <div class="file-upload-wrapper">
                    <!-- native input is visible (but styled by CSS to be transparent & overlay the visual button) -->
                    <input type="file" id="package_image" name="package_image" accept="image/*" required>
                    <button type="button" id="btn-choose-file" class="upload-button" tabindex="-1"><i class="bi bi-upload"></i> Choose File</button>
                    <span id="file-name-package" class="file-name" aria-live="polite">No file chosen</span>
                </div>
                <div class="field-error" aria-live="polite"></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="destination">Destination *</label>
                    <input type="text" id="destination" name="destination" placeholder="e.g., Skardu" required>
                    <div class="field-error" aria-live="polite"></div>
                </div>
                <div class="form-group">
                    <label for="duration_days">Duration (in days) *</label>
                    <input type="number" id="duration_days" name="duration_days" placeholder="e.g., 7" required>
                    <div class="field-error" aria-live="polite"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="price">Base Price Per Person (PKR) *</label>
                <input type="number" id="price" name="price" placeholder="e.g., 85000" step="100" required>
                <small>This price will apply to solo, family, or group travelers.</small>
                <div class="field-error" aria-live="polite"></div>
            </div>

            <!-- NEW: Suitable For Traveler Types -->
            <div class="form-group suitable-section">
                <label><strong>Suitable For:</strong></label><br>

                <label class="suitable-checkbox">
                    <input type="checkbox" name="suitable_for[]" value="family"> Family
                </label>

                <label class="suitable-checkbox">
                    <input type="checkbox" name="suitable_for[]" value="couple" id="couple_checkbox">
                    Couple / Honeymoon
                </label>

                <label class="suitable-checkbox">
                    <input type="checkbox" name="suitable_for[]" value="friends"> Friends Group
                </label>

                <label class="suitable-checkbox">
                    <input type="checkbox" name="suitable_for[]" value="solo"> Solo Traveler
                </label>

                <div class="field-error" aria-live="polite"></div>
            </div>

            <!-- NEW: Special Couple Price -->
            <div class="form-group" id="couple_price_wrapper" style="display:none;">
                <label><strong>Couple Price (Total for 2 persons):</strong></label>
                <input type="number" id="couple_price" name="couple_price" class="form-control" placeholder="e.g., 11000" step="100">
                <small>This price will be applied if 2 people book as a couple/honeymoon.</small>
                <div class="field-error" aria-live="polite"></div>
            </div>

            <!-- NEW: Package Description -->
            <div class="form-group package-description-group">
                <label for="description">Package Description *</label>
                <textarea id="description" name="description" rows="6"
                    placeholder="Describe the itinerary, highlights, what's included, exclusions, and any important notes..."
                    required></textarea>
                <small>Tip: Mention day-by-day plan, transport, hotels, and any special experiences.</small>
                <div class="field-error" aria-live="polite"></div>
            </div>

            <div class="form-actions form-actions-full">
                <a href="agency_my_packages.php" class="btn-cancel">Cancel</a>
                <button id="btnSubmit" type="submit" class="btn-publish"><i class="bi bi-check-lg"></i> Save Package</button>
            </div>
        </form>
    </div>
</div>

<!-- Validation modal (replaces browser alert) -->
<div id="validationModal" class="validation-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="validationTitle">
  <div class="validation-modal-content" role="document">
    <h3 id="validationTitle">Please fix the following before saving:</h3>
    <ul id="validationList" aria-live="polite"></ul>
    <div style="text-align:right; margin-top:12px;">
      <button id="validationClose" class="btn-publish" type="button">OK</button>
    </div>
  </div>
</div>

<style>
/* Minimal inline styles for the validation modal (paste into main CSS if you prefer) */
.validation-modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 18px;
}
.validation-modal[aria-hidden="false"] { display: flex; }
.validation-modal-content {
  background: #fff;
  padding: 20px;
  border-radius: 10px;
  max-width: 560px;
  width: 100%;
  box-shadow: 0 8px 30px rgba(0,0,0,0.2);
  font-family: inherit;
}
.validation-modal-content h3 { margin: 0 0 10px 0; font-size: 18px; }
.validation-modal-content ul { margin: 0 0 6px 18px; padding: 0; }
.validation-modal-content li { margin-bottom: 6px; }
.btn-publish { /* keep small button style consistent with your theme; adjust if needed */
  background: #0b5ed7;
  color: white;
  border: none;
  padding: 8px 14px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
}
.btn-publish:focus { outline: 3px solid rgba(11,94,215,0.25); }
.field-error { color: #c00; font-size: 13px; margin-top:6px; }
.invalid { box-shadow: 0 0 0 2px rgba(255,0,0,0.06) inset; }

/* The file upload visual button should look clickable but input above handles interaction */
.file-upload-wrapper .upload-button {
  cursor: default; /* actual click is handled by the native input placed on top by CSS */
}
</style>

<script>
/*
  Validation rules requested by the user.
  Change from previous: removed any JS that programmatically calls fileInput.click()
  to avoid accidentally opening the file picker twice. Native input (styled transparent)
  will capture clicks; we only update filename on change.
*/
(function(){
    const form = document.getElementById('packageForm');
    if (!form) return;

    const titleEl = document.getElementById('title');
    const destEl = document.getElementById('destination');
    const durationEl = document.getElementById('duration_days');
    const priceEl = document.getElementById('price');
    const descEl = document.getElementById('description');
    const coupleCheckbox = document.getElementById('couple_checkbox');
    const coupleWrapper = document.getElementById('couple_price_wrapper');
    const couplePriceEl = document.getElementById('couple_price');
    const fileInput = document.getElementById('package_image');
    const fileNameSpan = document.getElementById('file-name-package');

    const modal = document.getElementById('validationModal');
    const list = document.getElementById('validationList');
    const closeBtn = document.getElementById('validationClose');

    // Update filename display when user picks a file (no programmatic click)
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const f = fileInput.files[0];
            fileNameSpan.textContent = f ? f.name : 'No file chosen';
        });
    }

    if (coupleCheckbox) {
        coupleCheckbox.addEventListener('change', () => {
            coupleWrapper.style.display = coupleCheckbox.checked ? 'block' : 'none';
        });
    }

    function setError(el, msg) {
        if (!el) return;
        const container = el.closest('.form-group') || el.parentNode;
        const err = container.querySelector('.field-error');
        if (err) err.textContent = msg;
        el.classList.add('invalid');
    }
    function clearError(el) {
        if (!el) return;
        const container = el.closest('.form-group') || el.parentNode;
        const err = container.querySelector('.field-error');
        if (err) err.textContent = '';
        el.classList.remove('invalid');
    }

    function validateAll() {
        const errors = [];

        // 1) Title pattern
        const title = (titleEl.value || '').trim();
        const titlePattern = /^\d+(?:\s*[-]?\s*|\s+)(?:day|days)\b\s+[A-Za-z&'\-\s]+$/i;
        if (!title) {
            setError(titleEl, 'Package title is required.');
            errors.push('Package title is required.');
        } else if (!titlePattern.test(title)) {
            setError(titleEl, 'Title must start with duration like "10-day" followed by destination text (e.g., "10-day Naran Kaghan").');
            errors.push('Package title must start with duration like "10-day" followed by destination text.');
        } else {
            clearError(titleEl);
        }

        // 2) Destination: only letters, spaces and some punctuation (no digits)
        const dest = (destEl.value || '').trim();
        const destPattern = /^[A-Za-z&'\-\s]{2,}$/;
        if (!dest) {
            setError(destEl, 'Destination is required.');
            errors.push('Destination is required.');
        } else if (!destPattern.test(dest)) {
            setError(destEl, 'Destination must contain letters only (no numbers).');
            errors.push('Destination must contain letters only (no numbers).');
        } else {
            clearError(destEl);
        }

        // 2b) Duration: must be integer, no letters, at least 1
        const durVal = (durationEl.value || '').trim();
        if (durVal === '' || !/^[0-9]+$/.test(durVal) || parseInt(durVal,10) < 1) {
            setError(durationEl, 'Duration must be a positive whole number (days).');
            errors.push('Duration must be a positive whole number (days).');
        } else {
            clearError(durationEl);
        }

        // 3) price positive
        const priceValRaw = (priceEl.value || '').trim();
        const priceVal = parseFloat(priceValRaw);
        if (priceValRaw === '' || isNaN(priceVal) || priceVal <= 0) {
            setError(priceEl, 'Base price must be a positive number.');
            errors.push('Base price must be a positive number.');
        } else {
            clearError(priceEl);
        }

        // 3b) couple price positive (only if shown and non-empty)
        if (coupleCheckbox && coupleCheckbox.checked) {
            const cpRaw = (couplePriceEl.value || '').trim();
            if (cpRaw !== '') {
                const cpVal = parseFloat(cpRaw);
                if (isNaN(cpVal) || cpVal <= 0) {
                    setError(couplePriceEl, 'Couple price must be a positive number if entered.');
                    errors.push('Couple price must be a positive number if entered.');
                } else {
                    clearError(couplePriceEl);
                }
            } else {
                clearError(couplePriceEl);
            }
        } else {
            if (couplePriceEl) clearError(couplePriceEl);
        }

        // 4) description length >= 20
        const desc = (descEl.value || '').trim();
        if (!desc || desc.length < 20) {
            setError(descEl, 'Description must be at least 20 characters long.');
            errors.push('Description must be at least 20 characters long.');
        } else {
            clearError(descEl);
        }

        return errors;
    }

    // clear inline error on input
    form.querySelectorAll('input, textarea').forEach(el => {
        el.addEventListener('input', () => clearError(el));
    });

    // Modal helpers
    function showModalWithList(items) {
        if (!modal || !list) {
            // fallback to alert if modal doesn't exist
            alert("Please fix the following before saving:\n\n" + items.map((p,i)=> (i+1)+'. '+p).join("\n"));
            return;
        }
        list.innerHTML = '';
        items.forEach(p => {
            const li = document.createElement('li');
            li.textContent = p;
            list.appendChild(li);
        });
        modal.setAttribute('aria-hidden', 'false');
        // focus OK button
        setTimeout(() => {
            if (closeBtn) closeBtn.focus();
        }, 40);
    }

    function hideModal() {
        if (!modal) return;
        modal.setAttribute('aria-hidden', 'true');
        list.innerHTML = '';
    }

    // close on click outside
    if (modal) {
        modal.addEventListener('click', (ev) => {
            if (ev.target === modal) hideModal();
        });
    }

    // close on Esc
    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape' && modal && modal.getAttribute('aria-hidden') === 'false') {
            hideModal();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', hideModal);
    }

    form.addEventListener('submit', function(e){
        const problems = validateAll();
        if (problems.length > 0) {
            e.preventDefault();
            showModalWithList(problems);
            // scroll to first inline error
            const firstErrEl = form.querySelector('.invalid');
            if (firstErrEl) firstErrEl.scrollIntoView({behavior:'smooth', block:'center'});
            return false;
        }
        return true;
    });

})();
</script>

<?php
// 5. Includes the footer
require_once 'includes/agency_footer.php'; 
?>
