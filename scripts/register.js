
        document.addEventListener('DOMContentLoaded', function () {

            const form = document.getElementById('registerForm');

            const firstName = document.getElementById('firstName');
            const lastName = document.getElementById('lastName');
            const email = document.getElementById('email');
            const phone = document.getElementById('phone');
            const dob = document.getElementById('dob');
            const gender = document.getElementById('gender');
            const bloodGroup = document.getElementById('bloodGroup');

            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirmPassword');

            const terms = document.getElementById('terms');

            const patientOption = document.getElementById('patientOption');
            const doctorOption = document.getElementById('doctorOption');
            const doctorFields = document.getElementById('doctorFields');

            const licenseInput = document.getElementById('licenseNumber');
            const specializationInput = document.getElementById('specialization');
            const photoInput = document.getElementById('verificationPhoto');

            const fileName = document.getElementById('fileName');

            /*
            ============================================================
            HELPERS
            ============================================================
            */

            function getErrorElement(input) {

                let error = input.parentElement.parentElement.querySelector(
                    '.client-error'
                );

                if (!error) {
                    error = document.createElement('div');
                    error.className = 'client-error';

                    input.parentElement.parentElement.appendChild(error);
                }

                return error;
            }


            function showError(input, message) {

                const error = getErrorElement(input);

                error.textContent = message;
                error.style.display = 'block';

                input.classList.add('input-error');
                input.classList.remove('input-valid');

                input.setCustomValidity(message);
            }


            function showSuccess(input) {

                const error = getErrorElement(input);

                error.textContent = '';
                error.style.display = 'none';

                input.classList.remove('input-error');

                if (input.value.trim() !== '') {
                    input.classList.add('input-valid');
                }

                input.setCustomValidity('');
            }


            function clearValidation(input) {

                const error = getErrorElement(input);

                error.textContent = '';
                error.style.display = 'none';

                input.classList.remove('input-error', 'input-valid');
                input.setCustomValidity('');
            }


            /*
            ============================================================
            USER TYPE
            ============================================================
            */

            function isDoctor() {
                return doctorOption.querySelector('input').checked;
            }


            function activateDoctorFields(doctor) {

                if (doctor) {

                    doctorOption.classList.add('active');
                    patientOption.classList.remove('active');

                    doctorFields.classList.add('show');

                    licenseInput.setAttribute('required', 'required');
                    specializationInput.setAttribute('required', 'required');
                    photoInput.setAttribute('required', 'required');

                } else {

                    patientOption.classList.add('active');
                    doctorOption.classList.remove('active');

                    doctorFields.classList.remove('show');

                    licenseInput.removeAttribute('required');
                    specializationInput.removeAttribute('required');
                    photoInput.removeAttribute('required');

                    clearValidation(licenseInput);
                    clearValidation(specializationInput);
                    clearValidation(photoInput);
                }
            }


            patientOption.addEventListener('click', function () {
                activateDoctorFields(false);
            });


            doctorOption.addEventListener('click', function () {
                activateDoctorFields(true);
            });


            activateDoctorFields(
                doctorOption.querySelector('input').checked
            );


            /*
            ============================================================
            FIRST NAME
            ============================================================
            */

            function validateFirstName() {

                const value = firstName.value.trim();

                if (value === '') {
                    showError(firstName, 'First name is required.');
                    return false;
                }

                if (value.length > 50) {
                    showError(firstName, 'First name must not exceed 50 characters.');
                    return false;
                }

                if (!/^[a-zA-Z\s\-']+$/.test(value)) {
                    showError(
                        firstName,
                        'First name can contain only letters, spaces, hyphens and apostrophes.'
                    );
                    return false;
                }

                showSuccess(firstName);
                return true;
            }


            /*
            ============================================================
            LAST NAME
            ============================================================
            */

            function validateLastName() {

                const value = lastName.value.trim();

                if (value === '') {
                    showError(lastName, 'Last name is required.');
                    return false;
                }

                if (value.length > 50) {
                    showError(lastName, 'Last name must not exceed 50 characters.');
                    return false;
                }

                if (!/^[a-zA-Z\s\-']+$/.test(value)) {
                    showError(
                        lastName,
                        'Last name can contain only letters, spaces, hyphens and apostrophes.'
                    );
                    return false;
                }

                showSuccess(lastName);
                return true;
            }


            /*
            ============================================================
            EMAIL
            ============================================================
            */

            function validateEmail() {

                const value = email.value.trim();

                if (value === '') {
                    showError(email, 'Email address is required.');
                    return false;
                }

                if (value.length > 100) {
                    showError(email, 'Email address must not exceed 100 characters.');
                    return false;
                }

                const emailPattern =
                    /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;

                if (!emailPattern.test(value)) {
                    showError(email, 'Please enter a valid email address.');
                    return false;
                }

                showSuccess(email);
                return true;
            }


            /*
            ============================================================
            INDIAN MOBILE NUMBER
            ============================================================
            */

            function validatePhone() {

                let value = phone.value.trim();

                // Only digits
                value = value.replace(/\D/g, '').slice(0, 10);
                phone.value = value;

                if (value === '') {
                    showError(phone, 'Mobile number is required.');
                    return false;
                }

                if (!/^[6-9][0-9]{9}$/.test(value)) {
                    showError(
                        phone,
                        'Enter a valid 10-digit Indian mobile number starting with 6, 7, 8 or 9.'
                    );
                    return false;
                }

                showSuccess(phone);
                return true;
            }


            /*
            ============================================================
            DATE OF BIRTH / AGE
            ============================================================
            */

            function get18YearsAgo() {

                const today = new Date();

                today.setHours(0, 0, 0, 0);

                today.setFullYear(today.getFullYear() - 18);

                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');

                return `${today.getFullYear()}-${month}-${day}`;
            }


            function calculateAge(dateString) {

                const birthDate = new Date(dateString + 'T00:00:00');
                const today = new Date();

                let age = today.getFullYear() - birthDate.getFullYear();

                const monthDifference =
                    today.getMonth() - birthDate.getMonth();

                if (
                    monthDifference < 0 ||
                    (
                        monthDifference === 0 &&
                        today.getDate() < birthDate.getDate()
                    )
                ) {
                    age--;
                }

                return age;
            }


            dob.max = get18YearsAgo();


            function validateDOB() {

                const value = dob.value;

                if (value === '') {
                    showError(dob, 'Date of birth is required.');
                    return false;
                }

                const selectedDate = new Date(value + 'T00:00:00');
                const today = new Date();

                today.setHours(0, 0, 0, 0);

                if (isNaN(selectedDate.getTime())) {
                    showError(dob, 'Please enter a valid date of birth.');
                    return false;
                }

                if (selectedDate > today) {
                    showError(dob, 'Date of birth cannot be in the future.');
                    return false;
                }

                const age = calculateAge(value);

                if (age < 18) {
                    showError(
                        dob,
                        'You must be 18 years or older to register.'
                    );
                    return false;
                }

                if (age > 120) {
                    showError(
                        dob,
                        'Please enter a valid date of birth.'
                    );
                    return false;
                }

                showSuccess(dob);
                return true;
            }


            /*
            ============================================================
            GENDER
            ============================================================
            */

            function validateGender() {

                if (!['male', 'female', 'other'].includes(gender.value)) {

                    showError(gender, 'Please select your gender.');

                    return false;
                }

                showSuccess(gender);

                return true;
            }


            /*
            ============================================================
            BLOOD GROUP
            ============================================================
            */

            function validateBloodGroup() {

                const allowed = [
                    '',
                    'A+',
                    'A-',
                    'B+',
                    'B-',
                    'AB+',
                    'AB-',
                    'O+',
                    'O-'
                ];

                if (!allowed.includes(bloodGroup.value)) {

                    showError(
                        bloodGroup,
                        'Please select a valid blood group.'
                    );

                    return false;
                }

                clearValidation(bloodGroup);

                return true;
            }


            /*
            ============================================================
            PASSWORD
            ============================================================
            */

            function validatePassword() {

                const value = password.value;

                if (value.length < 8) {

                    showError(
                        password,
                        'Password must contain at least 8 characters.'
                    );

                    return false;
                }

                if (!/[A-Z]/.test(value)) {

                    showError(
                        password,
                        'Password must contain at least one uppercase letter.'
                    );

                    return false;
                }

                if (!/[a-z]/.test(value)) {

                    showError(
                        password,
                        'Password must contain at least one lowercase letter.'
                    );

                    return false;
                }

                if (!/[0-9]/.test(value)) {

                    showError(
                        password,
                        'Password must contain at least one number.'
                    );

                    return false;
                }

                showSuccess(password);

                return true;
            }


            /*
            ============================================================
            CONFIRM PASSWORD
            ============================================================
            */

            function validateConfirmPassword() {

                if (confirmPassword.value === '') {

                    showError(
                        confirmPassword,
                        'Please confirm your password.'
                    );

                    return false;
                }

                if (confirmPassword.value !== password.value) {

                    showError(
                        confirmPassword,
                        'Passwords do not match.'
                    );

                    return false;
                }

                showSuccess(confirmPassword);

                return true;
            }


            /*
            ============================================================
            DOCTOR LICENSE
            ============================================================
            */

            function validateLicense() {

                if (!isDoctor()) {
                    clearValidation(licenseInput);
                    return true;
                }

                const value = licenseInput.value.trim();

                if (value === '') {

                    showError(
                        licenseInput,
                        'Medical license number is required.'
                    );

                    return false;
                }

                if (!/^[a-zA-Z0-9\-\/]{3,50}$/.test(value)) {

                    showError(
                        licenseInput,
                        'Enter a valid medical license number.'
                    );

                    return false;
                }

                showSuccess(licenseInput);

                return true;
            }


            /*
            ============================================================
            SPECIALIZATION
            ============================================================
            */

            function validateSpecialization() {

                if (!isDoctor()) {
                    clearValidation(specializationInput);
                    return true;
                }

                const allowed = [
                    'general',
                    'cardiology',
                    'dermatology',
                    'neurology',
                    'orthopedics',
                    'pediatrics',
                    'psychiatry',
                    'surgery',
                    'other'
                ];

                if (!allowed.includes(specializationInput.value)) {

                    showError(
                        specializationInput,
                        'Please select a specialization.'
                    );

                    return false;
                }

                showSuccess(specializationInput);

                return true;
            }


            /*
            ============================================================
            DOCTOR VERIFICATION PHOTO
            ============================================================
            */

            function validatePhoto() {

                if (!isDoctor()) {
                    clearValidation(photoInput);
                    return true;
                }

                const file = photoInput.files[0];

                if (!file) {

                    showError(
                        photoInput,
                        'Please upload your medical license or ID photo.'
                    );

                    return false;
                }

                const allowedTypes = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];

                const maxSize = 5 * 1024 * 1024;

                if (!allowedTypes.includes(file.type)) {

                    showError(
                        photoInput,
                        'Only JPG, PNG or WEBP images are allowed.'
                    );

                    photoInput.value = '';
                    fileName.textContent = '';

                    return false;
                }

                if (file.size > maxSize) {

                    showError(
                        photoInput,
                        'File size must not exceed 5MB.'
                    );

                    photoInput.value = '';
                    fileName.textContent = '';

                    return false;
                }

                fileName.textContent = 'Selected: ' + file.name;

                showSuccess(photoInput);

                return true;
            }


            /*
            ============================================================
            TERMS
            ============================================================
            */

            function validateTerms() {

                if (!terms.checked) {

                    terms.setCustomValidity(
                        'You must agree to the Terms & Conditions and Privacy Policy.'
                    );

                    return false;
                }

                terms.setCustomValidity('');

                return true;
            }


            /*
            ============================================================
            LIVE VALIDATION
            ============================================================
            */

            firstName.addEventListener('blur', validateFirstName);
            lastName.addEventListener('blur', validateLastName);
            email.addEventListener('blur', validateEmail);

            phone.addEventListener('input', validatePhone);
            phone.addEventListener('blur', validatePhone);

            dob.addEventListener('change', validateDOB);
            dob.addEventListener('blur', validateDOB);

            gender.addEventListener('change', validateGender);
            bloodGroup.addEventListener('change', validateBloodGroup);

            password.addEventListener('input', function () {
                validatePassword();

                if (confirmPassword.value !== '') {
                    validateConfirmPassword();
                }
            });

            confirmPassword.addEventListener(
                'input',
                validateConfirmPassword
            );

            licenseInput.addEventListener(
                'input',
                validateLicense
            );

            specializationInput.addEventListener(
                'change',
                validateSpecialization
            );

            photoInput.addEventListener(
                'change',
                validatePhoto
            );

            terms.addEventListener(
                'change',
                validateTerms
            );


            /*
            ============================================================
            FORM SUBMISSION
            ============================================================
            */

            form.addEventListener('submit', function (event) {

                /*
                 * Stop normal browser submission first.
                 */
                event.preventDefault();

                /*
                 * Validate EVERYTHING.
                 */
                const valid = [
                    validateFirstName(),
                    validateLastName(),
                    validateEmail(),
                    validatePhone(),
                    validateDOB(),
                    validateGender(),
                    validateBloodGroup(),
                    validatePassword(),
                    validateConfirmPassword(),
                    validateLicense(),
                    validateSpecialization(),
                    validatePhoto(),
                    validateTerms()
                ].every(Boolean);


                /*
                 * If ANY validation fails:
                 * DO NOT continue to verify.php.
                 */
                if (!valid) {

                    const firstInvalid =
                        form.querySelector('.input-error');

                    if (firstInvalid) {

                        firstInvalid.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });

                        firstInvalid.focus();
                    }

                    return;
                }


                /*
                 * Everything is valid.
                 *
                 * Now allow the PHP POST request.
                 */
                form.submit();
            });

        });
