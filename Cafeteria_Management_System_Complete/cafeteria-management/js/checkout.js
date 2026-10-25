/* checkout.js - Razorpay integration */
document.addEventListener("DOMContentLoaded", () => {
  if (document.body.dataset.page === "checkout") initCheckout();
  else if (document.body.dataset.page === "success") initSuccess();
});

async function initCheckout() {
  const user = typeof requireLogin === 'function' ? await requireLogin() : null;
  if (!user) return;
  const cart = typeof refreshCart === 'function' ? await refreshCart() : (typeof getCart === 'function' ? getCart() : []);
  if (!cart.length) { location.replace("cart.html"); return; }

  const form = document.getElementById("checkoutForm");
  if(form.fullName) form.fullName.value = user.name || ''; 
  if(form.phone) form.phone.value = user.phone || ''; 
  if(form.email) form.email.value = user.email || '';

  const orderSummary = document.getElementById("orderSummary");
  if(orderSummary){
    orderSummary.innerHTML = cart.map((i) => '<li class="d-flex justify-content-between py-2 border-bottom"><span>'+esc(i.name)+' <span class="text-muted">x '+i.qty+'</span></span><span>'+fmtINR(i.price*i.qty)+'</span></li>').join("");
  }
  const total = typeof cartTotal === 'function' ? cartTotal() : cart.reduce((s,i)=>s+Number(i.price||0)*Number(i.qty||0),0);
  const coTotal = document.getElementById("coTotal");
  const payBtnAmount = document.getElementById("payBtnAmount");
  if(coTotal) coTotal.textContent = fmtINR(total);
  if(payBtnAmount) payBtnAmount.textContent = fmtINR(total);

  const payModalEl = document.getElementById("payModal");
  const payModal = payModalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(payModalEl) : null;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (typeof validateForm === 'function' && !validateForm(form)) return;
    const msg = document.getElementById("payMsg"), spinner = document.getElementById("paySpinner"), done = document.getElementById("payDone");
    if(spinner) spinner.classList.remove("d-none"); 
    if(done) done.classList.add("d-none"); 
    if(msg) msg.textContent = "Creating secure order...";
    if(payModal) payModal.show();
    try {
      const currentCart = typeof getCart === 'function' ? getCart() : cart;
      const orderResp = await createRazorpayOrder({
        customerName: form.fullName ? form.fullName.value.trim() : (user.name||''),
        phone: form.phone ? form.phone.value.trim() : (user.phone||''),
        email: form.email ? form.email.value.trim() : (user.email||''),
        pickup: form.pickup ? form.pickup.value : 'As soon as possible',
        notes: form.notes ? form.notes.value.trim() : '',
        items: currentCart
      });
      if(msg) msg.textContent = "Opening Razorpay Checkout...";
      const options = {
        key: orderResp.keyId,
        amount: orderResp.amountPaise,
        currency: orderResp.currency || 'INR',
        name: "Campus CafAc",
        description: "Food Order Payment",
        order_id: orderResp.razorpayOrderId,
        prefill: { name: form.fullName?form.fullName.value.trim():'', email: form.email?form.email.value.trim():'', contact: form.phone?form.phone.value.trim():'' },
        theme: { color: "#0d6efd" },
        handler: async function (response) {
          if(msg) msg.textContent = "Verifying payment...";
          try {
            const verifyResp = await verifyRazorpayPayment({
              orderRef: orderResp.orderRef,
              razorpay_order_id: response.razorpay_order_id,
              razorpay_payment_id: response.razorpay_payment_id,
              razorpay_signature: response.razorpay_signature
            });
            if(spinner) spinner.classList.add("d-none"); 
            if(done) done.classList.remove("d-none"); 
            if(msg) msg.textContent = "Payment verified! Redirecting...";
            if(typeof clearCart === 'function') clearCart();
            const orderId = (verifyResp.order && (verifyResp.order.id||verifyResp.order.order_ref)) ? (verifyResp.order.id||verifyResp.order.order_ref) : orderResp.orderRef;
            setTimeout(() => { location.href = 'order-success.html?id='+orderId; }, 800);
          } catch (err) {
            if(payModal) payModal.hide();
            if(typeof showToast === 'function') showToast(err.message, "danger"); else alert(err.message);
          }
        },
        modal: { ondismiss: function() { if(payModal) payModal.hide(); if(typeof showToast === 'function') showToast("Payment cancelled", "warning"); } }
      };
      const rzp = new Razorpay(options);
      rzp.open();
      if(payModal) payModal.hide();
    } catch (err) {
      if(payModal) payModal.hide();
      if(typeof showToast === 'function') showToast(err.message, "danger"); else alert(err.message);
    }
  });
}

async function initSuccess() {
  const user = typeof requireLogin === 'function' ? await requireLogin() : null;
  if (!user) return;
  const box = document.getElementById("successBox");
  try {
    const orderId = new URLSearchParams(location.search).get("id");
    const order = await getOrderById(orderId);
    if (order.userId && order.userId !== user.id && user.role !== 'ADMIN') throw new Error("Not your order");
    const items = order.items || [];
    let itemsHtml = '';
    for(let i=0;i<items.length;i++){ 
      const it = items[i];
      const name = it.name||it.item_name||'';
      const price = it.price||it.unit_price||0;
      const qty = it.qty||it.quantity||0;
      itemsHtml += '<tr><td>'+esc(name)+'</td><td class="text-end">'+fmtINR(price)+'</td><td class="text-center">'+qty+'</td><td class="text-end">'+fmtINR(price*qty)+'</td></tr>';
    }
    box.innerHTML = '<div class="text-center mb-4"><div class="success-tick"><i class="bi bi-check-lg"></i></div><h1 class="h2 mt-3">Order confirmed!</h1><p class="text-muted mb-0">Payment received and verified.</p></div>'+
      '<div class="card bill-card"><div class="card-body p-4"><div class="row g-3 mb-3">'+
      '<div class="col-6 col-md-3"><div class="small text-muted">Order ID</div><div class="fw-bold">'+esc(order.id||order.order_ref)+'</div></div>'+
      '<div class="col-6 col-md-3"><div class="small text-muted">Date & time</div><div>'+(typeof fmtDate==='function'?fmtDate(order.date||order.created_at):(order.date||order.created_at||''))+'</div></div>'+
      '<div class="col-6 col-md-3"><div class="small text-muted">Payment</div><div>'+(order.paymentMethod||order.payment_method||'RAZORPAY')+' '+(typeof statusBadge==='function'?statusBadge(order.paymentStatus||order.payment_status):'')+'</div></div>'+
      '<div class="col-6 col-md-3"><div class="small text-muted">Status</div><div>'+(typeof statusBadge==='function'?statusBadge(order.status):order.status)+'</div></div>'+
      '</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Item</th><th class="text-end">Price</th><th class="text-center">Qty</th><th class="text-end">Amount</th></tr></thead><tbody>'+itemsHtml+'</tbody><tfoot><tr><th colspan="3" class="text-end">Total paid</th><th class="text-end fs-5">'+fmtINR(order.total)+'</th></tr></tfoot></table></div>'+
      (order.notes?'<p class="small text-muted mt-3 mb-0"><i class="bi bi-sticky me-1"></i>Note: '+esc(order.notes)+'</p>':'')+
      '</div></div><div class="d-flex flex-wrap justify-content-center gap-2 mt-4 no-print"><a href="menu.html" class="btn btn-outline-secondary">Back to menu</a><a href="orders.html" class="btn btn-primary">View my orders</a><button class="btn btn-outline-dark" onclick="window.print()">Print bill</button></div>';
  } catch (err) {
    box.innerHTML = '<div class="text-center py-5"><h3 class="mt-3">Order not found</h3><a href="orders.html" class="btn btn-primary mt-2">Go to my orders</a></div>';
  }
}
