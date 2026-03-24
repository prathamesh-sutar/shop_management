/**
 * Surveillance Shop Management System – Main JS
 */

/* ---- Sidebar toggle ---- */
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('sidebarToggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                document.body.classList.toggle('sidebar-open');
            } else {
                document.body.classList.toggle('sidebar-collapsed');
            }
        });
    }

    // Fetch low stock count
    fetchLowStockCount();

    // Auto-dismiss alerts after 5 s
    document.querySelectorAll('.alert.fade.show').forEach(function (el) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            bsAlert.close();
        }, 5000);
    });

    // Confirm delete buttons
    document.querySelectorAll('[data-confirm]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });
});

/* ---- Low stock count ---- */
function fetchLowStockCount() {
    const badge = document.getElementById('lowStockCount');
    const btn   = document.getElementById('lowStockBtn');
    if (!badge) return;

    fetch(getBasePath() + '/api/inventory_api.php?action=low_stock_count')
        .then(r => r.json())
        .then(data => {
            if (data.count > 0) {
                badge.textContent = data.count;
                if (btn) btn.classList.remove('d-none');
            }
        })
        .catch(() => {}); // silently ignore
}

/* ---- Resolve base path from current URL ---- */
function getBasePath() {
    const parts = window.location.pathname.split('/').filter(Boolean);
    // If first segment is a known sub-folder (not a php page), it's the project root
    if (parts.length > 0 && !parts[0].includes('.php')) {
        return '/' + parts[0];
    }
    return '';
}

/* ---- Generic AJAX helper ---- */
function ajaxPost(url, data) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json());
}

/* ---- Format currency ---- */
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/* ---- Sales: add product row ---- */
let saleRowIndex = 0;

function addSaleRow(product) {
    const tbody = document.getElementById('saleItemsBody');
    if (!tbody) return;

    saleRowIndex++;
    const idx = saleRowIndex;
    const row = document.createElement('tr');
    row.id = 'saleRow_' + idx;
    row.innerHTML = `
        <td>
            <input type="hidden" name="items[${idx}][product_id]" value="${product.product_id}">
            ${escHtml(product.product_name)}
            <small class="text-muted d-block">${escHtml(product.brand || '')}</small>
        </td>
        <td>${formatCurrency(product.price)}</td>
        <td style="width:100px">
            <input type="number" class="form-control form-control-sm qty-input"
                   name="items[${idx}][quantity]" value="1"
                   min="1" max="${product.stock_quantity}"
                   data-price="${product.price}"
                   data-idx="${idx}"
                   onchange="updateRowSubtotal(${idx})">
        </td>
        <td id="subtotal_${idx}">${formatCurrency(product.price)}</td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="removeSaleRow(${idx})">
                <i class="bi bi-trash"></i>
            </button>
        </td>`;
    tbody.appendChild(row);
    updateSaleTotals();
}

function removeSaleRow(idx) {
    const row = document.getElementById('saleRow_' + idx);
    if (row) row.remove();
    updateSaleTotals();
}

function updateRowSubtotal(idx) {
    const qty   = parseFloat(document.querySelector(`[data-idx="${idx}"]`).value) || 0;
    const price = parseFloat(document.querySelector(`[data-idx="${idx}"]`).dataset.price) || 0;
    const sub   = qty * price;
    const el    = document.getElementById('subtotal_' + idx);
    if (el) el.textContent = formatCurrency(sub);
    updateSaleTotals();
}

function updateSaleTotals() {
    let total = 0;
    document.querySelectorAll('.qty-input').forEach(function (input) {
        const qty   = parseFloat(input.value) || 0;
        const price = parseFloat(input.dataset.price) || 0;
        total += qty * price;
    });

    const discountEl = document.getElementById('discountAmount');
    const taxEl      = document.getElementById('taxAmount');
    const discount   = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const taxRate    = taxEl      ? parseFloat(taxEl.value)      || 0 : 0;
    const tax        = (total - discount) * (taxRate / 100);
    const grand      = total - discount + tax;

    setElText('totalAmount',  formatCurrency(total));
    setElText('taxAmountCalc', formatCurrency(tax));
    setElText('grandTotal',   formatCurrency(grand));

    const grandInput = document.getElementById('grandTotalInput');
    if (grandInput) grandInput.value = grand.toFixed(2);
}

function setElText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
