/* =====================================================================
 * api.js  - PHP Backend API integration
 * ===================================================================== */

const API_BASE_URL = (function(){
  // Try to detect local PHP API location
  return "http://localhost/cafeteria-management/api";
})();

async function apiRequest(method, path, body) {
  const res = await fetch(API_BASE_URL + path, {
    method,
    headers: { "Content-Type": "application/json" },
    credentials: 'include',
    body: body ? JSON.stringify(body) : undefined,
  });
  const text = await res.text();
  let data;
  try { data = text ? JSON.parse(text) : {}; } catch(e) { data = { message: text || 'Request failed' }; }
  if (!res.ok) throw new Error(data.message || 'Request failed');
  return data;
}

async function phpGet(path){ return apiRequest('GET', path); }
async function phpPost(path, body){ return apiRequest('POST', path, body); }
async function phpPatch(path, body){ return apiRequest('PATCH', path, body); }

async function loginUserPhp(email, password){
  const res = await phpPost('/auth/login', { email, password });
  return res.user;
}
async function registerUserPhp({name,email,phone,password}){
  return phpPost('/auth/register', {name,email,phone,password});
}
async function logoutUserPhp(){ try { await phpPost('/auth/logout', {}); } catch(e){} }
async function getCurrentUserPhp(){ try { return await phpGet('/auth/me'); } catch(e){ return null; } }

async function createRazorpayOrder({customerName,phone,email,pickup,notes,items}){
  return phpPost('/payments/create-order', { customerName, phone, email, pickup, notes, items: items.map(i=>({id:i.id, qty:i.qty})) });
}
async function verifyRazorpayPayment(payload){
  return phpPost('/payments/verify', payload);
}

/* =====================================================================
 * Compatibility shims (fallback to mock if PHP not available)
 * ===================================================================== */
const USE_MOCK = true;
const DB_KEYS = ["users", "categories", "foodItems", "orders", "feedback"];
const SEED_FLAG = "cms_seeded_v1";
function dbGet(name) {
  try { return JSON.parse(localStorage.getItem("cms_" + name)) || []; } catch (e) { return []; }
}
function dbSet(name, value) { localStorage.setItem("cms_" + name, JSON.stringify(value)); }
function initMockData() {
  if (typeof MOCK_DATA !== 'undefined') {
    if (localStorage.getItem(SEED_FLAG)) {
      // Migrate default remote food photos to the bundled local copies without
      // resetting users, orders, or custom administrator images.
      const defaultImages = new Map(MOCK_DATA.foodItems.map((item) => [item.id, item.image]));
      const items = dbGet("foodItems");
      let changed = false;
      const migrated = items.map((item) => {
        if (typeof item.image === "string" && item.image.startsWith("https://images.unsplash.com/photo-")) {
          changed = true;
          return { ...item, image: defaultImages.get(item.id) || item.image };
        }
        return item;
      });
      if (changed) dbSet("foodItems", migrated);
      return;
    }
    DB_KEYS.forEach((k) => { if(MOCK_DATA[k]) dbSet(k, MOCK_DATA[k]); });
    localStorage.setItem(SEED_FLAG, "1");
  }
}
function resetDemoData() {
  Object.keys(localStorage).filter((k) => k.startsWith("cms_")).forEach((k) => localStorage.removeItem(k));
  sessionStorage.removeItem("cms_session");
  initMockData();
}
initMockData();
const nextId = (list) => list.reduce((m, x) => Math.max(m, Number(x.id) || 0), 0) + 1;
const stripPassword = ({ password, ...safe }) => safe;
const fail = (msg) => Promise.reject(new Error(msg));

async function loginUser(email, password, remember = false) {
  try {
    const user = await loginUserPhp(email,password);
    const store = remember ? localStorage : sessionStorage;
    localStorage.removeItem("cms_session"); sessionStorage.removeItem("cms_session");
    store.setItem("cms_session", JSON.stringify({ userId: user.id }));
    return user;
  } catch(e) {
    const user = dbGet("users").find((u) => u.email.toLowerCase() === email.trim().toLowerCase());
    if (!user || user.password !== password) return fail(e.message || "Invalid email or password.");
    if (user.status === "Blocked") return fail("This account has been blocked. Please contact the cafeteria admin.");
    const store = remember ? localStorage : sessionStorage;
    localStorage.removeItem("cms_session"); sessionStorage.removeItem("cms_session");
    store.setItem("cms_session", JSON.stringify({ userId: user.id }));
    return stripPassword(user);
  }
}
async function registerUser({ name, email, phone, password }) {
  try { return await registerUserPhp({name,email,phone,password}); } catch(e) {
    const users = dbGet("users");
    if (users.some((u) => u.email.toLowerCase() === email.trim().toLowerCase())) return fail("An account with this email already exists.");
    const user = { id: nextId(users), name: name.trim(), email: email.trim(), phone: phone.trim(), password, registeredOn: new Date().toISOString(), status: "Active" };
    users.push(user); dbSet("users", users); return stripPassword(user);
  }
}
function logoutUser() {
  try { logoutUserPhp(); } catch(e){}
  localStorage.removeItem("cms_session"); sessionStorage.removeItem("cms_session");
}
async function getCurrentUser() {
  try {
    const u = await getCurrentUserPhp();
    if(u) return u;
  } catch(e){}
  const raw = sessionStorage.getItem("cms_session") || localStorage.getItem("cms_session");
  if (!raw) return null;
  try {
    const user = dbGet("users").find((u) => u.id === JSON.parse(raw).userId);
    return user && user.status !== "Blocked" ? stripPassword(user) : null;
  } catch (e) { return null; }
}

async function getCategories() { 
  try { const c = await phpGet('/categories'); if(Array.isArray(c)) return c; } catch(e){}
  return dbGet("categories"); 
}
function useBundledFoodPhoto(item) {
  const bundled = MOCK_DATA.foodItems.find((food) => Number(food.id) === Number(item.id));
  return bundled ? { ...item, image: bundled.image } : item;
}
async function getFoodItems() { 
  try { const f = await phpGet('/food-items'); if(Array.isArray(f)) return f.map(x=>({...useBundledFoodPhoto(x), categoryId: x.categoryId||x.category_id})); } catch(e){}
  return dbGet("foodItems"); 
}
async function getFoodItem(id) {
  try { return useBundledFoodPhoto(await phpGet('/food-items/'+id)); } catch(e){}
  const item = dbGet("foodItems").find((f) => f.id === Number(id));
  return item || fail("Food item not found.");
}

async function createOrder({ userId, customerName, phone, email, items, paymentMethod, pickup, notes }) {
  const orders = dbGet("orders");
  const total = items.reduce((sum, i) => sum + Number(i.price||0) * Number(i.qty||0), 0);
  const order = {
    id: "ORD-" + (1000 + nextId(orders.map((o) => ({ id: String(o.id).replace("ORD-", "") - 1000 })))),
    userId, customerName, phone, email, items, total, paymentMethod,
    paymentStatus: "Paid", status: "Pending", pickup, notes, date: new Date().toISOString(),
  };
  orders.push(order); dbSet("orders", orders); return order;
}
async function getOrders(userId = null) {
  try {
    if(userId){ const my = await phpGet('/orders/my'); if(Array.isArray(my)) return my; }
    else { const all = await phpGet('/admin/orders'); if(Array.isArray(all)) return all; }
  }catch(e){}
  const list = dbGet("orders").sort((a, b) => new Date(b.date) - new Date(a.date));
  return userId ? list.filter((o) => o.userId === userId) : list;
}
async function getOrderById(id) {
  try { const o = await phpGet('/orders/'+id); if(o && o.order_ref) return {...o, id:o.order_ref}; }catch(e){}
  return dbGet("orders").find((o) => o.id === id) || fail("Order not found.");
}
async function updateOrderStatus(id, status) {
  try { return await phpPatch('/admin/orders/'+id+'/status', {status}); }catch(e){}
  const orders = dbGet("orders");
  const order = orders.find((o) => o.id === id);
  if (!order) return fail("Order not found.");
  order.status = status;
  dbSet("orders", orders); return order;
}

async function submitFeedback({ userId, name, rating, message }) {
  try { return await phpPost('/feedback', {rating,message}); }catch(e){}
  const list = dbGet("feedback");
  const entry = { id: nextId(list), userId, name, rating: Number(rating), message: message.trim(), date: new Date().toISOString() };
  list.push(entry); dbSet("feedback", list); return entry;
}
async function getFeedback() { try { const f = await phpGet('/feedback'); if(Array.isArray(f)) return f; }catch(e){} return dbGet("feedback").sort((a, b) => new Date(b.date) - new Date(a.date)); }

async function adminLogin(email, password) {
  if (email.trim().toLowerCase() !== (typeof MOCK_DATA!=='undefined'?MOCK_DATA.admin?.email:'') || password !== (typeof MOCK_DATA!=='undefined'?MOCK_DATA.admin?.password:'')) return fail("Invalid admin credentials.");
  sessionStorage.setItem("cms_admin_session", JSON.stringify({ name: MOCK_DATA.admin.name, email: MOCK_DATA.admin.email }));
  return { name: MOCK_DATA.admin.name, email: MOCK_DATA.admin.email };
}
function adminLogout() { sessionStorage.removeItem("cms_admin_session"); }
function getAdminSession() {
  try { return JSON.parse(sessionStorage.getItem("cms_admin_session")); } catch (e) { return null; }
}
async function getAdminDashboard() {
  try { const d = await phpGet('/admin/dashboard'); if(d) return d; }catch(e){}
  const orders = dbGet("orders");
  const valid = orders.filter((o) => o.status !== "Cancelled");
  const popular = {};
  valid.forEach((o) => (o.items||[]).forEach((i) => { popular[i.name] = (popular[i.name] || 0) + Number(i.qty||0); }));
  const days = [];
  for (let n = 6; n >= 0; n--) {
    const d = new Date(); d.setDate(d.getDate() - n);
    const key = d.toDateString();
    days.push({ label: d.toLocaleDateString("en-IN", { weekday: "short" }), revenue: valid.filter((o) => new Date(o.date).toDateString() === key).reduce((s, o) => s + Number(o.total||0), 0) });
  }
  return {
    totalOrders: orders.length,
    totalCustomers: dbGet("users").length,
    totalFoodItems: dbGet("foodItems").length,
    totalRevenue: valid.reduce((s, o) => s + Number(o.total||0), 0),
    pendingOrders: orders.filter((o) => o.status === "Pending").length,
    recentOrders: [...orders].sort((a, b) => new Date(b.date) - new Date(a.date)).slice(0, 5),
    popularItems: Object.entries(popular).map(([name, qty]) => ({ name, qty })).sort((a, b) => b.qty - a.qty).slice(0, 5),
    revenueByDay: days,
  };
}
async function addFoodItem(data) {
  const items = dbGet("foodItems");
  const item = { id: nextId(items), image: "", popular: false, ...data };
  items.push(item); dbSet("foodItems", items); return item;
}
async function updateFoodItem(id, data) {
  const items = dbGet("foodItems");
  const idx = items.findIndex((f) => f.id === Number(id));
  if (idx < 0) return fail("Food item not found.");
  items[idx] = { ...items[idx], ...data };
  dbSet("foodItems", items); return items[idx];
}
async function deleteFoodItem(id) {
  dbSet("foodItems", dbGet("foodItems").filter((f) => f.id !== Number(id)));
  return true;
}
async function addCategory(data) {
  const list = dbGet("categories");
  if (list.some((c) => c.name.toLowerCase() === data.name.trim().toLowerCase())) return fail("A category with this name already exists.");
  const cat = { id: nextId(list), icon: "bi-tag", status: "Active", ...data };
  list.push(cat); dbSet("categories", list); return cat;
}
async function updateCategory(id, data) {
  const list = dbGet("categories");
  const idx = list.findIndex((c) => c.id === Number(id));
  if (idx < 0) return fail("Category not found.");
  list[idx] = { ...list[idx], ...data };
  dbSet("categories", list); return list[idx];
}
async function deleteCategory(id) {
  if (dbGet("foodItems").some((f) => f.categoryId === Number(id))) return fail("This category still has food items. Move or delete them first.");
  dbSet("categories", dbGet("categories").filter((c) => c.id !== Number(id)));
  return true;
}
async function getUsers() { return dbGet("users").map(stripPassword); }
async function updateUserStatus(id, status) {
  const users = dbGet("users");
  const u = users.find((x) => x.id === Number(id));
  if (!u) return fail("User not found.");
  u.status = status; dbSet("users", users); return stripPassword(u);
}
