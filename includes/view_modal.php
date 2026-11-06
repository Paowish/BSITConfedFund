<?php
// Shared Approve/Deny/View Modal + Lightbox + Toast
// Include this in any page that has approve/deny buttons.
?>
<style>
    .btn-view { background:#3498db; color:#fff; padding:5px 10px; border:none; border-radius:4px; cursor:pointer; font-size:12px; margin-right:4px; }
    .btn-view:hover { background:#2980b9; }
    .btn-approve { background:#27ae60; color:white; padding:5px 12px; border:none; border-radius:5px; cursor:pointer; font-size:12px; }
    .btn-approve:hover { background:#219a52; }
    .btn-deny { background:#e74c3c; color:white; padding:5px 12px; border:none; border-radius:5px; cursor:pointer; font-size:12px; margin-left:4px; }
    .btn-deny:hover { background:#c0392b; }

    .vmodal-overlay {
        display:none; position:fixed; top:0; left:0; width:100%; height:100%;
        background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;
    }
    .vmodal-overlay.active { display:flex; }
    .vmodal-box {
        background:#fff; border-radius:12px; width:520px; max-width:92%; max-height:88vh;
        overflow-y:auto; padding:30px; position:relative; box-shadow:0 10px 40px rgba(0,0,0,0.2);
    }
    .vmodal-close {
        position:absolute; top:15px; right:20px; background:none; border:none;
        font-size:24px; color:#888; cursor:pointer; line-height:1;
    }
    .vmodal-close:hover { color:#333; }
    .vmodal-box h3 { margin-bottom:20px; display:flex; align-items:center; gap:10px; font-size:18px; }
    .vmodal-box h3.danger { color:#e74c3c; }
    .vmodal-box h3.success { color:#27ae60; }
    .vmodal-box h3.info { color:#2980b9; }
    .vmodal-box p { color:#555; font-size:14px; margin-bottom:20px; line-height:1.5; }
    .vmodal-box label { display:block; font-weight:600; margin-bottom:6px; font-size:13px; color:#333; }
    .vmodal-box textarea {
        width:100%; padding:12px; border:1px solid #d1d5db; border-radius:6px;
        font-family:inherit; font-size:14px; margin-bottom:20px; resize:vertical; min-height:100px;
    }
    .vmodal-box textarea:focus { outline:none; border-color:#e74c3c; box-shadow:0 0 0 2px rgba(231,76,60,0.1); }
    .vmodal-actions { display:flex; gap:10px; justify-content:flex-end; }
    .vmodal-btn-cancel { background:#95a5a6; color:#fff; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:14px; }
    .vmodal-btn-cancel:hover { background:#7f8c8d; }
    .vmodal-btn-approve { background:#27ae60; color:#fff; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:14px; }
    .vmodal-btn-approve:hover { background:#219a52; }
    .vmodal-btn-deny { background:#e74c3c; color:#fff; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:14px; }
    .vmodal-btn-deny:hover { background:#c0392b; }

    /* View Modal - larger */
    .vmodal-box.wide { width:640px; }

    .vdetail-row { display:flex; padding:10px 0; border-bottom:1px solid #f0f0f0; font-size:14px; }
    .vdetail-row:last-child { border-bottom:none; }
    .vdetail-label { font-weight:600; color:#555; width:150px; flex-shrink:0; }
    .vdetail-value { color:#1f2937; flex:1; }
    .vsection-title {
        font-weight:700; color:#1a3c5e; margin-top:20px; margin-bottom:12px;
        padding-bottom:5px; border-bottom:2px solid #e2e8f0;
        font-size:13px; text-transform:uppercase; letter-spacing:0.5px;
    }
    .vdocs-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:12px; margin-top:10px; }
    .vdoc-item { border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; cursor:pointer; transition:transform 0.2s, box-shadow 0.2s; background:#fff; }
    .vdoc-item:hover { transform:translateY(-3px); box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .vdoc-item img { width:100%; height:110px; object-fit:cover; display:block; }
    .vdoc-item .vdoc-label { padding:6px 8px; font-size:11px; color:#555; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; background:#f8f9fa; }
    .vdoc-item.pdf { background:#fdf3f3; }
    .vdoc-item.pdf .vdoc-icon { height:110px; display:flex; align-items:center; justify-content:center; font-size:40px; color:#e74c3c; }

    .vlightbox { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:10001; align-items:center; justify-content:center; }
    .vlightbox.active { display:flex; }
    .vlightbox img { max-width:90%; max-height:90%; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,0.5); }
    .vlightbox-close { position:absolute; top:20px; right:30px; background:none; border:none; color:#fff; font-size:32px; cursor:pointer; }

    .vtoast {
        position:fixed; top:20px; right:20px; background:#27ae60; color:#fff;
        padding:15px 25px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15);
        font-weight:600; font-size:14px; z-index:10000; opacity:0; transform:translateY(-20px);
        transition:all 0.3s;
    }
    .vtoast.show { opacity:1; transform:translateY(0); }
    .vtoast.error { background:#e74c3c; }
</style>

<!-- Approve Modal -->
<div id="approveModal" class="vmodal-overlay">
    <div class="vmodal-box">
        <button class="vmodal-close" onclick="closeApproveModal()">&times;</button>
        <h3 class="success"><i class="fas fa-check-circle"></i> Approve Entry</h3>
        <p>Are you sure you want to approve <strong id="approveEntryName"></strong>?</p>
        <form id="approveModalForm" method="POST" onsubmit="return submitApproveForm(event)">
            <input type="hidden" name="type" id="approveType">
            <input type="hidden" name="id" id="approveId">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="comment" value="">
            <div class="vmodal-actions">
                <button type="button" class="vmodal-btn-cancel" onclick="closeApproveModal()">Cancel</button>
                <button type="submit" class="vmodal-btn-approve"><i class="fas fa-check"></i> Confirm Approve</button>
            </div>
        </form>
    </div>
</div>

<!-- Deny Modal -->
<div id="denyModal" class="vmodal-overlay">
    <div class="vmodal-box">
        <button class="vmodal-close" onclick="closeDenyModal()">&times;</button>
        <h3 class="danger"><i class="fas fa-exclamation-triangle"></i> Deny Entry</h3>
        <p>Are you sure you want to deny <strong id="denyEntryName"></strong>? Please provide a reason.</p>
        <form id="denyModalForm" method="POST" onsubmit="return submitDenyForm(event)">
            <input type="hidden" name="type" id="denyType">
            <input type="hidden" name="id" id="denyId">
            <input type="hidden" name="action" value="deny">
            <label>Reason for Denial <span style="color:red;">*</span></label>
            <textarea name="comment" id="denyReason" placeholder="Enter reason here..." required></textarea>
            <div class="vmodal-actions">
                <button type="button" class="vmodal-btn-cancel" onclick="closeDenyModal()">Cancel</button>
                <button type="submit" class="vmodal-btn-deny"><i class="fas fa-times"></i> Confirm Deny</button>
            </div>
        </form>
    </div>
</div>

<!-- View Modal -->
<div id="viewModal" class="vmodal-overlay">
    <div class="vmodal-box wide">
        <button class="vmodal-close" onclick="closeViewModal()">&times;</button>
        <h3 class="info"><i class="fas fa-eye"></i> Entry Details</h3>
        <div id="viewContent"></div>
    </div>
</div>

<!-- Lightbox -->
<div id="vlightbox" class="vlightbox">
    <button class="vlightbox-close" onclick="closeLightbox()">&times;</button>
    <img id="vlightboxImage" src="" alt="Preview">
</div>

<!-- Toast -->
<div id="vtoast" class="vtoast"></div>

<script>
    // ============ APPROVE MODAL ============
    function openApproveModal(type, id, name) {
        document.getElementById('approveType').value = type;
        document.getElementById('approveId').value = id;
        document.getElementById('approveEntryName').textContent = name;
        document.getElementById('approveModal').classList.add('active');
    }

    function closeApproveModal() {
        document.getElementById('approveModal').classList.remove('active');
    }

    function submitApproveForm(e) {
        e.preventDefault();
        document.getElementById('approveModalForm').submit();
        return false;
    }

    // ============ DENY MODAL ============
    function openDenyModal(type, id, name) {
        document.getElementById('denyType').value = type;
        document.getElementById('denyId').value = id;
        document.getElementById('denyEntryName').textContent = name;
        document.getElementById('denyReason').value = '';
        document.getElementById('denyModal').classList.add('active');
        setTimeout(function() { document.getElementById('denyReason').focus(); }, 100);
    }

    function closeDenyModal() {
        document.getElementById('denyModal').classList.remove('active');
    }

    function submitDenyForm(e) {
        e.preventDefault();
        var reason = document.getElementById('denyReason').value.trim();
        if (reason === '') {
            alert('Please provide a reason for denial.');
            return false;
        }
        document.getElementById('denyModalForm').submit();
        return false;
    }

    // ============ VIEW MODAL ============
    function openViewModal(data) {
        var html = '';

        if (data.type) html += '<div class="vdetail-row"><div class="vdetail-label">Type</div><div class="vdetail-value">' + data.type + '</div></div>';
        if (data.status) html += '<div class="vdetail-row"><div class="vdetail-label">Status</div><div class="vdetail-value">' + data.status + '</div></div>';
        if (data.submitted_by) html += '<div class="vdetail-row"><div class="vdetail-label">Submitted By</div><div class="vdetail-value">' + data.submitted_by + '</div></div>';
        if (data.source) html += '<div class="vdetail-row"><div class="vdetail-label">Source</div><div class="vdetail-value">' + data.source + '</div></div>';
        if (data.category) html += '<div class="vdetail-row"><div class="vdetail-label">Category</div><div class="vdetail-value">' + data.category + '</div></div>';
        if (data.payee) html += '<div class="vdetail-row"><div class="vdetail-label">Who Paid</div><div class="vdetail-value">' + data.payee + '</div></div>';
        if (data.amount) html += '<div class="vdetail-row"><div class="vdetail-label">Amount</div><div class="vdetail-value">₱' + data.amount + '</div></div>';
        if (data.date) html += '<div class="vdetail-row"><div class="vdetail-label">Date</div><div class="vdetail-value">' + data.date + '</div></div>';
        if (data.school_year) html += '<div class="vdetail-row"><div class="vdetail-label">School Year</div><div class="vdetail-value">' + data.school_year + '</div></div>';
        if (data.fund_source) html += '<div class="vdetail-row"><div class="vdetail-label">Fund Source</div><div class="vdetail-value">' + data.fund_source + '</div></div>';
        if (data.event) html += '<div class="vdetail-row"><div class="vdetail-label">Event</div><div class="vdetail-value">' + data.event + '</div></div>';
        if (data.description) html += '<div class="vdetail-row"><div class="vdetail-label">Description</div><div class="vdetail-value">' + data.description + '</div></div>';

        if (data.reviewer) {
            html += '<div class="vsection-title">Reviewer Information</div>';
            html += '<div class="vdetail-row"><div class="vdetail-label">Reviewed By</div><div class="vdetail-value">' + data.reviewer + '</div></div>';
            if (data.reviewed_at) html += '<div class="vdetail-row"><div class="vdetail-label">Reviewed At</div><div class="vdetail-value">' + data.reviewed_at + '</div></div>';
            if (data.comment) html += '<div class="vdetail-row"><div class="vdetail-label">Comment</div><div class="vdetail-value">' + data.comment + '</div></div>';
        }

        html += '<div class="vsection-title">Source Documents</div>';
        if (data.documents && data.documents.length > 0) {
            html += '<div class="vdocs-grid">';
            data.documents.forEach(function(doc) {
                if (doc.type === 'image') {
                    html += '<div class="vdoc-item" onclick="openLightbox(\'' + doc.url + '\')">' +
                                '<img src="' + doc.url + '" alt="">' +
                                '<div class="vdoc-label">' + doc.name + '</div>' +
                            '</div>';
                } else {
                    html += '<div class="vdoc-item pdf" onclick="window.open(\'' + doc.url + '\', \'_blank\')">' +
                                '<div class="vdoc-icon"><i class="fas fa-file-pdf"></i></div>' +
                                '<div class="vdoc-label">' + doc.name + '</div>' +
                            '</div>';
                }
            });
            html += '</div>';
        } else {
            html += '<p style="color:#999;font-size:13px;">No documents attached</p>';
        }

        document.getElementById('viewContent').innerHTML = html;
        document.getElementById('viewModal').classList.add('active');
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.remove('active');
    }

    function openLightbox(url) {
        document.getElementById('vlightboxImage').src = url;
        document.getElementById('vlightbox').classList.add('active');
    }
    function closeLightbox() {
        document.getElementById('vlightbox').classList.remove('active');
    }

    function showVToast(message, isError) {
        var toast = document.getElementById('vtoast');
        toast.textContent = message;
        if (isError) toast.classList.add('error');
        else toast.classList.remove('error');
        toast.classList.add('show');
        setTimeout(function() { toast.classList.remove('show'); }, 3000);
    }

    // Close modals when clicking outside
    document.getElementById('approveModal').addEventListener('click', function(e) {
        if (e.target === this) closeApproveModal();
    });
    document.getElementById('denyModal').addEventListener('click', function(e) {
        if (e.target === this) closeDenyModal();
    });
    document.getElementById('viewModal').addEventListener('click', function(e) {
        if (e.target === this) closeViewModal();
    });
    document.getElementById('vlightbox').addEventListener('click', function(e) {
        if (e.target === this) closeLightbox();
    });
</script>