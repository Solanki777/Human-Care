
// ============================================================
// PRESCRIPTION MODAL
// ============================================================

/**
 * Stores the currently selected prescription.
 * Initially, no prescription is selected.
 */
let currentRx = null;


/**
 * Opens the prescription modal and displays its details.
 *
 * @param {Object} data - Prescription information, including
 * patient, doctor, date, prescription ID, and medicine items.
 */
function openRxModal(data) {
    // Store the selected prescription.
    currentRx = data;

    // Display patient, doctor, and prescription date.
    document.getElementById('mi-patient').textContent = data.patient;
    document.getElementById('mi-doctor').textContent = 'Dr. ' + data.doctor;
    document.getElementById('mi-date').textContent = data.date;

    // Populate hidden fields in the prescription forms.
    // These fields identify the appointment and prescription.
    ['fa', 'fe', 'fc'].forEach(prefix => {
        document.getElementById(prefix + '-aid').value = data.aid;
        document.getElementById(prefix + '-rxid').value = data.rx_id;
    });

    // Generate the prescription's read-only view.
    buildViewTable(data);

    // Generate the editable prescription table.
    buildEditTable(data);

    // Open the modal on the view tab by default.
    switchTab('view');

    // Display the modal and prevent background scrolling.
    document.getElementById('rxModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}


/**
 * Closes the prescription modal and restores page scrolling.
 */
function closeRxModal() {
    document.getElementById('rxModal').classList.remove('open');
    document.body.style.overflow = '';
}


// Close the modal when the user clicks outside its content.
document.getElementById('rxModal').addEventListener('click', function (e) {
    if (e.target === this) {
        closeRxModal();
    }
});


// ============================================================
// TAB SWITCHING
// ============================================================

/**
 * Switches between the prescription view and edit tabs.
 *
 * @param {string} tab - The tab to display: 'view' or 'edit'.
 */
function switchTab(tab) {
    const isEdit = tab === 'edit';

    // Update the active tab styling.
    document.getElementById('tab-view').classList.toggle('active', !isEdit);
    document.getElementById('tab-edit').classList.toggle('active', isEdit);

    // Show one section and hide the other.
    document.getElementById('viewSection').style.display = isEdit ? 'none' : 'block';
    document.getElementById('editSection').style.display = isEdit ? 'block' : 'none';

    // Show the appropriate action button.
    document.getElementById('btnOpenEdit').style.display = isEdit ? 'none' : '';
    document.getElementById('btnSaveEdit').style.display = isEdit ? '' : 'none';
}


// ============================================================
// BUILD PRESCRIPTION VIEW TABLE
// ============================================================

/**
 * Builds the read-only table containing prescription medicines.
 * Also displays the diagnosis, total price, and notes.
 *
 * @param {Object} data - The selected prescription data.
 */
function buildViewTable(data) {
    const tbody = document.getElementById('viewTbody');

    // Clear previous prescription rows.
    tbody.innerHTML = '';

    // Initialize the total medicine price.
    let total = 0;

    // Display the diagnosis if available.
    const diagBox = document.getElementById('view-diag');
    const diagText = document.getElementById('view-diag-text');

    if (data.diagnosis) {
        diagBox.style.display = '';
        diagText.textContent = data.diagnosis;
    } else {
        diagBox.style.display = 'none';
    }

    // Create a table row for each prescribed medicine.
    (data.items || []).forEach((item, idx) => {
        // Convert the medicine price to a number.
        const price = parseFloat(item.price_at_time) || 0;

        // Add the price to the total.
        total += price;

        // Create a new table row.
        const tr = document.createElement('tr');

        // Populate the row with escaped medicine information.
        tr.innerHTML = `
            <td style="color:#8b5cf6;font-weight:700;">
                ${idx + 1}
            </td>
            <td>
                <div style="font-weight:700;color:#1e293b;">
                    ${esc(item.medicine_name)}
                </div>
                <div style="font-size:11px;color:#64748b;">
                    ${esc(item.category || '')}
                </div>
            </td>
            <td style="font-size:12px;color:#64748b;">
                ${esc(item.dosage_form || '')}${item.strength ? ' · ' + esc(item.strength) : ''}
            </td>
            <td>${esc(item.dosage || '—')}</td>
            <td>${esc(item.duration || '—')}</td>
            <td style="font-size:12px;">
                ${esc(item.instructions || '—')}
            </td>
            <td style="font-weight:700;color:#8b5cf6;">
                ₹${price.toFixed(2)}
            </td>
        `;

        // Add the row to the table.
        tbody.appendChild(tr);
    });

    // Display the total medicine price.
    document.getElementById('viewTotal').textContent =
        '₹' + total.toFixed(2);

    // Display prescription notes if available.
    const notesBox = document.getElementById('view-notes-box');
    const notesText = document.getElementById('view-notes-text');

    if (data.notes) {
        notesBox.style.display = '';
        notesText.textContent = data.notes;
    } else {
        notesBox.style.display = 'none';
    }
}


// ============================================================
// BUILD PRESCRIPTION EDIT TABLE
// ============================================================

/**
 * Prepares the editable prescription form.
 * Populates the diagnosis, notes, and medicine rows.
 *
 * @param {Object} data - The selected prescription data.
 */
function buildEditTable(data) {
    // Populate diagnosis and notes fields.
    document.getElementById('editDiagnosis').value = data.diagnosis || '';
    document.getElementById('editNotes').value = data.notes || '';

    const tbody = document.getElementById('editTbody');

    // Remove any previously displayed medicine rows.
    tbody.innerHTML = '';

    // Create an editable row for each medicine.
    (data.items || []).forEach((item, idx) => {
        appendEditRow(tbody, idx, item);
    });
}


/**
 * Creates and appends one editable medicine row.
 *
 * @param {HTMLElement} tbody - The table body.
 * @param {number} idx - The medicine's row index.
 * @param {Object} item - The medicine information.
 */
function appendEditRow(tbody, idx, item) {
    const tr = document.createElement('tr');

    // Store the row index as a data attribute.
    tr.dataset.idx = idx;

    // Create the editable fields and hidden medicine data.
    tr.innerHTML = `
        <td style="color:#1e3c72;font-weight:700;">
            ${idx + 1}
        </td>
        <td>
            <input type="text" class="edit-inp"
                   style="min-width:130px;"
                   data-field="name"
                   value="${esc(item.medicine_name || '')}"
                   placeholder="Medicine name" readonly>

            <input type="hidden" data-field="id"
                   value="${item.medicine_id || 0}">

            <input type="hidden" data-field="price"
                   value="${parseFloat(item.price_at_time || 0).toFixed(2)}">
        </td>
        <td>₹${parseFloat(item.price_at_time || 0).toFixed(2)}</td>
        <td>
            <input type="text" class="edit-inp"
                   data-field="dosage"
                   value="${esc(item.dosage || '')}"
                   placeholder="e.g. 1 tablet twice daily">
        </td>
        <td>
            <input type="text" class="edit-inp"
                   data-field="duration"
                   value="${esc(item.duration || '')}"
                   placeholder="e.g. 5 days">
        </td>
        <td>
            <input type="text" class="edit-inp"
                   style="min-width:140px;"
                   data-field="instructions"
                   value="${esc(item.instructions || '')}"
                   placeholder="After meals…">
        </td>
        <td>
            <button type="button" class="del-row-btn"
                    onclick="removeEditRow(this)">✕</button>
        </td>
    `;

    // Append the new row to the editable table.
    tbody.appendChild(tr);
}


// ============================================================
// REMOVE MEDICINE ROW
// ============================================================

/**
 * Removes a medicine from the editable prescription table.
 *
 * @param {HTMLElement} btn - The clicked delete button.
 */
function removeEditRow(btn) {
    // Find and remove the medicine's table row.
    const tr = btn.closest('tr');
    tr.remove();

    // Update row numbers after deletion.
    document.querySelectorAll('#editTbody tr').forEach((row, index) => {
        row.cells[0].textContent = index + 1;
    });
}


// ============================================================
// SUBMIT PRESCRIPTION EDIT
// ============================================================

/**
 * Collects the edited prescription data and submits the form.
 * Creates hidden inputs for the PHP backend to process.
 */
function submitEdit() {
    const feFields = document.getElementById('fe-fields');

    // Clear previously generated hidden inputs.
    feFields.innerHTML = '';

    // Add diagnosis and notes to the form.
    addHidden(
        feFields,
        'edit_diagnosis',
        document.getElementById('editDiagnosis').value
    );

    addHidden(
        feFields,
        'edit_notes',
        document.getElementById('editNotes').value
    );

    // Collect data from every remaining medicine row.
    document.querySelectorAll('#editTbody tr').forEach(tr => {
        addHidden(feFields, 'edit_med_id[]',
            tr.querySelector('[data-field="id"]').value);

        addHidden(feFields, 'edit_med_name[]',
            tr.querySelector('[data-field="name"]').value);

        addHidden(feFields, 'edit_med_price[]',
            tr.querySelector('[data-field="price"]').value);

        addHidden(feFields, 'edit_dosage[]',
            tr.querySelector('[data-field="dosage"]').value);

        addHidden(feFields, 'edit_duration[]',
            tr.querySelector('[data-field="duration"]').value);

        addHidden(feFields, 'edit_instructions[]',
            tr.querySelector('[data-field="instructions"]').value);
    });

    // Submit the completed form to the PHP backend.
    document.getElementById('formEdit').submit();
}


// ============================================================
// CREATE HIDDEN INPUT
// ============================================================

/**
 * Creates a hidden input and appends it to a form.
 *
 * @param {HTMLElement} parent - The element receiving the input.
 * @param {string} name - The input's name attribute.
 * @param {string} value - The input's value.
 */
function addHidden(parent, name, value) {
    const input = document.createElement('input');

    input.type = 'hidden';
    input.name = name;
    input.value = value;

    // Add the input to the specified parent.
    parent.appendChild(input);
}


// ============================================================
// HTML ESCAPE HELPER
// ============================================================

/**
 * Escapes HTML-sensitive characters to prevent HTML injection
 * when inserting text into HTML templates.
 *
 * @param {*} str - The value to escape.
 * @returns {string} The escaped string.
 */
function esc(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
