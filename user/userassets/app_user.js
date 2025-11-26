/*
  VisionDrive User Flow JS (User-side)
  File: app_user.js
  Location: /user/userassets/app_user.js
*/

(function () {
  const nameRegex = /^[a-zA-Z\s]{1,120}$/;
  const phoneRegex = /^[0-9]{7,10}$/;

  function validateDetailsForm(form) {
    let ok = true;

    const firstName = form.querySelector('#first_name');
    if (firstName) {
      const v = firstName.value.trim();
      if (!nameRegex.test(v)) {
        markError(firstName, "First Name must only contain letters/spaces.");
        ok = false;
      } else clearError(firstName);
    }

    const email = form.querySelector('#email');
    if (email) {
      const v = email.value.trim();
      if (!v || !v.includes('@') || !v.includes('.')) {
        markError(email, "Please enter a valid email address.");
        ok = false;
      } else clearError(email);
    }

    const phone = form.querySelector('#phone');
    if (phone) {
      const v = phone.value.trim();
      if (!phoneRegex.test(v)) {
        markError(phone, "Phone must be 7-10 digits.");
        ok = false;
      } else clearError(phone);
    }

    const region = form.querySelector('#region');
    if (region) {
      const v = region.value.trim();
      if (!v) {
        markError(region, "Region is required.");
        ok = false;
      } else clearError(region);
    }

    const fileInput = form.querySelector('#identity_doc');
    if (fileInput && fileInput.files && fileInput.files[0]) {
      const file = fileInput.files[0];
      const allowed = ['image/jpeg', 'image/png', 'application/pdf'];
      if (!allowed.includes(file.type)) {
        markError(fileInput, "Only JPG, PNG, or PDF allowed.");
        ok = false;
      } else if (file.size > 2 * 1024 * 1024) {
        markError(fileInput, "File too large (max 2MB).");
        ok = false;
      } else {
        clearError(fileInput);
      }
    } else {
      clearError(fileInput);
    }

    return ok;
  }

  function markError(inputEl, msg) {
    if (!inputEl) return;
    inputEl.classList.add('input-error');

    let group = inputEl.closest('.form-group');
    if (!group) return;
    let err = group.querySelector('.field-error');
    if (!err) {
      err = document.createElement('div');
      err.className = 'field-error';
      err.setAttribute('role', 'alert');
      group.appendChild(err);
    }
    err.textContent = msg;
  }

  function clearError(inputEl) {
    if (!inputEl) return;
    inputEl.classList.remove('input-error');
    let group = inputEl.closest('.form-group');
    if (!group) return;
    let err = group.querySelector('.field-error');
    if (err) {
      err.textContent = '';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    const detailsForm = document.querySelector('form[action="/user/details.php"]');
    if (detailsForm) {
      detailsForm.addEventListener('submit', function (e) {
        if (!validateDetailsForm(detailsForm)) {
          e.preventDefault();
          const firstErr = detailsForm.querySelector('.input-error');
          if (firstErr && firstErr.focus) {
            firstErr.focus();
          }
        }
      });
    }
  });
})();
