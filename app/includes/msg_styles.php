<style>
/* ── Shared Messaging Styles ── */
.msg-tabs{display:flex;gap:4px;border-bottom:2px solid #ECF0F1;margin-bottom:20px;}
.msg-tab{padding:10px 20px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:600;color:#95A5A6;border-bottom:3px solid transparent;margin-bottom:-2px;display:flex;align-items:center;gap:6px;transition:all .2s;}
.msg-tab.active{color:#3498DB;border-bottom-color:#3498DB;}
.msg-tab:hover{color:#2C3E50;}
.tab-pane{display:none;}.tab-pane.active{display:block;}
.unread-dot{background:#E74C3C;color:#fff;border-radius:50%;width:18px;height:18px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;}
.role-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;}
.role-manager{background:#EBF5FB;color:#2980B9;}
.role-employee{background:#EAFAF1;color:#27AE60;}
.role-admin{background:#F9EBEA;color:#C0392B;}
.role-customer{background:#FEF9E7;color:#D4AC0D;}
.unread-row{background:#F0F8FF;font-weight:600;}
.msg-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#2C3E50,#3D1F14);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;}
.action-btns{display:flex;gap:6px;flex-wrap:wrap;}
.btn-view{padding:5px 11px;background:#3498DB18;color:#3498DB;border:1.5px solid #3498DB55;border-radius:7px;font-size:11px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;}
.btn-reply{padding:5px 11px;background:#27AE6018;color:#27AE60;border:1.5px solid #27AE6055;border-radius:7px;font-size:11px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;}
.btn-del{padding:5px 9px;background:#E74C3C18;color:#E74C3C;border:1.5px solid #E74C3C55;border-radius:7px;font-size:11px;cursor:pointer;display:inline-flex;align-items:center;}
.s-new{background:#EBF5FB;color:#2980B9;padding:3px 9px;border-radius:12px;font-size:10px;font-weight:700;}
.s-read{background:#ECF0F1;color:#95A5A6;padding:3px 9px;border-radius:12px;font-size:10px;font-weight:700;}
.s-sent{background:#EAFAF1;color:#27AE60;padding:3px 9px;border-radius:12px;font-size:10px;font-weight:700;}
.msg-empty{text-align:center;padding:60px 20px;color:#95A5A6;}
.msg-empty i{font-size:52px;opacity:.2;display:block;margin-bottom:14px;}
.msg-empty div{font-size:15px;font-weight:600;}
/* Modals */
.msg-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;padding:16px;}
.msg-modal.open{display:flex;}
.msg-modal-box{background:#fff;border-radius:14px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);}
.msg-modal-head{padding:18px 22px;background:linear-gradient(135deg,#1a252f,#2C3E50);color:#fff;border-radius:14px 14px 0 0;display:flex;justify-content:space-between;align-items:center;}
.msg-modal-head h3{margin:0;font-size:15px;}
.msg-modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:14px;}
.msg-modal-body{padding:22px;}
.msg-modal-foot{padding:14px 22px;border-top:1px solid #F0F0F0;display:flex;gap:10px;justify-content:flex-end;}
.fg{margin-bottom:14px;}
.fg label{display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:5px;text-transform:uppercase;}
.fg-input{width:100%;padding:9px 12px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:13px;outline:none;box-sizing:border-box;}
.fg-input:focus{border-color:#3498DB;}
.msg-meta{background:#EBF5FB;border-radius:8px;padding:12px 16px;margin-bottom:14px;font-size:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.msg-meta strong{color:#2C3E50;}
.msg-body-box{background:#F8F9FA;border:1.5px solid #E0E0E0;border-radius:10px;padding:16px;white-space:pre-wrap;font-size:13px;line-height:1.7;min-height:80px;}
.msg-btn-primary{padding:10px 22px;background:linear-gradient(135deg,#2C3E50,#3D1F14);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;}
.msg-btn-cancel{padding:10px 18px;background:#F0F2F5;color:#555;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;}
/* Toast */
.msg-toast{position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.15);animation:slideUp .3s ease;}
.msg-toast-success{background:#27AE60;color:#fff;}
.msg-toast-error{background:#E74C3C;color:#fff;}
@keyframes slideUp{from{transform:translateY(20px);opacity:0;}to{transform:translateY(0);opacity:1;}}
</style>
