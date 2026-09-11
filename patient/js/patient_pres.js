
        function printThis(btn) {
            const card = btn.closest('.rx-card');
            const w = window.open('', '', 'width=850,height=700');
            w.document.write(`<!DOCTYPE html><html><head><title>Prescription – Human Care</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 24px; color: #1e293b; }
        h2 { color: #667eea; margin-bottom: 4px; }
        .rx-header { background: linear-gradient(135deg,#667eea,#764ba2); color:#fff;
            padding:18px 22px; border-radius:10px 10px 0 0; margin-bottom:0; }
        .rx-header h3 { margin:0; font-size:18px; }
        .rx-body { padding:20px; border:1px solid #e2e8f0; border-radius:0 0 10px 10px; }
        table { width:100%; border-collapse:collapse; margin:16px 0; }
        th { background:#667eea; color:#fff; padding:10px 12px; text-align:left; font-size:12px; }
        td { padding:10px 12px; border-bottom:1px solid #f1f5f9; font-size:13px; }
        .total-row td { font-weight:700; background:#f0f9ff; }
        .diag-box { padding:12px 16px; background:#fffbeb; border-left:4px solid #f59e0b;
            border-radius:6px; margin:14px 0; }
        .notes-box { padding:12px 16px; background:#e0e7ff; border-left:4px solid #667eea;
            border-radius:6px; margin:14px 0; }
        .print-btn { display:none; }
        .doctor-strip { background:#f8fafc; padding:12px 16px; border-radius:8px; margin-bottom:14px;
            display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
        .ds-label { font-size:10px; color:#94a3b8; text-transform:uppercase; }
        .ds-value { font-size:13px; font-weight:600; }
        .brand { text-align:center; margin-bottom:20px; border-bottom:2px solid #667eea; padding-bottom:14px; }
    </style></head><body>
    <div class="brand">
        <h2>❤️ HUMAN CARE</h2>
        <p style="margin:0;color:#64748b;">Medical Prescription</p>
    </div>
    ${card.outerHTML}
    </body></html>`);
            w.document.close();
            setTimeout(() => { w.print(); w.close(); }, 350);
        }
