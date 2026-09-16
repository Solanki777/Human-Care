'use strict';

// ============================================================
// MEDICINE STATE
// ============================================================

const selected = {}; // id → {name, price, form, strength}


// ============================================================
// TOGGLE MEDICINE
// ============================================================

function toggleMed(card) {

    const id = card.dataset.id;

    if (selected[id]) {

        card.classList.remove('selected');

        delete selected[id];

    } else {

        card.classList.add('selected');

        selected[id] = {
            name: card.dataset.name,
            price: parseFloat(card.dataset.price),
            form: card.dataset.form,
            strength: card.dataset.strength
        };
    }

    renderSelected();
}


// ============================================================
// RENDER SELECTED MEDICINES
// ============================================================

function renderSelected() {

    const tbody =
        document.getElementById('selectedBody');

    const section =
        document.getElementById('selectedSection');

    const saveBtn =
        document.getElementById('saveBtn');

    const totalPrice =
        document.getElementById('totalPrice');

    if (!tbody || !section || !saveBtn || !totalPrice) {
        return;
    }

    const ids = Object.keys(selected);

    // No medicines selected
    if (ids.length === 0) {

        tbody.innerHTML = '';

        section.style.display = 'none';

        saveBtn.disabled = true;

        totalPrice.textContent = '₹0.00';

        return;
    }

    section.style.display = '';

    saveBtn.disabled = false;

    let rows = '';

    let total = 0;

    ids.forEach(function (id, i) {

        const m = selected[id];

        total += Number(m.price) || 0;

        rows += `
            <tr>

                <td style="color:#667eea;font-weight:700;">
                    ${i + 1}
                </td>

                <td>

                    <input
                        type="hidden"
                        name="medicine_id[]"
                        value="${escapeHtml(id)}"
                    >

                    <div class="tbl-med-name">
                        ${escapeHtml(m.name)}
                    </div>

                    <div class="tbl-med-meta">
                        ${escapeHtml(m.form)}
                        ·
                        ${escapeHtml(m.strength)}
                    </div>

                </td>

                <td class="tbl-price">
                    ₹${Number(m.price).toFixed(2)}
                </td>

                <td>

                    <input
                        type="text"
                        name="dosage[]"
                        class="tbl-input"
                        placeholder="e.g. 1 tablet twice daily"
                    >

                </td>

                <td>

                    <input
                        type="text"
                        name="duration[]"
                        class="tbl-input"
                        placeholder="e.g. 5 days"
                    >

                </td>

                <td>

                    <input
                        type="text"
                        name="instructions[]"
                        class="tbl-input"
                        placeholder="Take after meals…"
                    >

                </td>

                <td>

                    <button
                        type="button"
                        class="del-btn"
                        onclick="removeMed('${escapeHtml(id)}', this)"
                    >
                        ✕
                    </button>

                </td>

            </tr>
        `;
    });

    tbody.innerHTML = rows;

    totalPrice.textContent =
        '₹' + total.toFixed(2);
}


// ============================================================
// REMOVE MEDICINE
// ============================================================

function removeMed(id, btn) {

    delete selected[id];

    const card =
        document.getElementById('medcard_' + id);

    if (card) {
        card.classList.remove('selected');
    }

    renderSelected();
}


// ============================================================
// CATEGORY FILTER
// ============================================================

function filterCat(cat, btn) {

    document
        .querySelectorAll('.cat-tab')
        .forEach(function (button) {

            button.classList.remove('active');

        });

    if (btn) {
        btn.classList.add('active');
    }

    document
        .querySelectorAll('.med-item')
        .forEach(function (card) {

            card.style.display =
                (
                    cat === 'all' ||
                    card.dataset.cat === cat
                )
                    ? ''
                    : 'none';

        });
}


// ============================================================
// SEARCH MEDICINES
// ============================================================

function searchMeds() {

    const searchInput =
        document.getElementById('medSearch');

    if (!searchInput) {
        return;
    }

    const q =
        searchInput.value
            .toLowerCase()
            .trim();

    document
        .querySelectorAll('.med-item')
        .forEach(function (card) {

            const name =
                (card.dataset.name || '')
                    .toLowerCase();

            card.style.display =
                name.includes(q)
                    ? ''
                    : 'none';

        });
}


// ============================================================
// RESTORE SAVED MEDICINES
// ============================================================

function restoreSavedMedicines() {

    const savedItems =
        window.savedMedicines || [];

    if (!Array.isArray(savedItems) ||
        savedItems.length === 0) {

        renderSelected();

        return;
    }

    // Select saved medicine cards
    savedItems.forEach(function (item) {

        const id =
            String(item.medicine_id);

        const card =
            document.getElementById(
                'medcard_' + id
            );

        if (!card) {
            return;
        }

        card.classList.add('selected');

        selected[id] = {

            name: card.dataset.name,

            price:
                parseFloat(
                    card.dataset.price
                ),

            form:
                card.dataset.form,

            strength:
                card.dataset.strength
        };
    });

    // Render table
    renderSelected();

    // Restore dosage / duration / instructions
    savedItems.forEach(function (item, index) {

        const dosageInputs =
            document.querySelectorAll(
                'input[name="dosage[]"]'
            );

        const durationInputs =
            document.querySelectorAll(
                'input[name="duration[]"]'
            );

        const instructionInputs =
            document.querySelectorAll(
                'input[name="instructions[]"]'
            );

        if (dosageInputs[index]) {

            dosageInputs[index].value =
                item.dosage || '';
        }

        if (durationInputs[index]) {

            durationInputs[index].value =
                item.duration || '';
        }

        if (instructionInputs[index]) {

            instructionInputs[index].value =
                item.instructions || '';
        }

    });
}


// ============================================================
// HTML ESCAPE
// ============================================================

function escapeHtml(value) {

    const div =
        document.createElement('div');

    div.textContent =
        value == null
            ? ''
            : String(value);

    return div.innerHTML;
}


// ============================================================
// INITIALIZE
// ============================================================

document.addEventListener(
    'DOMContentLoaded',
    function () {

        restoreSavedMedicines();

    }
);