/* =====================================================================
 * mockData.js  -  CENTRALIZED SEED DATA
 * ===================================================================== */

function daysAgo(n, hh = 12, mm = 0) {
  const d = new Date();
  d.setDate(d.getDate() - n);
  d.setHours(hh, mm, 0, 0);
  return d.toISOString();
}

const MOCK_DATA = {
  admin: { name: "Cafeteria Admin", email: "admin@cafeteria.com", password: "admin123" },

  categories: [
    { id: 1, name: "Breakfast", icon: "bi-brightness-high", description: "South-Indian classics to start the day", status: "Active" },
    { id: 2, name: "Snacks", icon: "bi-basket", description: "Quick bites between lectures", status: "Active" },
    { id: 3, name: "Fast Food", icon: "bi-lightning-charge", description: "Burgers, rolls and pizza", status: "Active" },
    { id: 4, name: "Meals", icon: "bi-egg-fried", description: "Filling lunch options", status: "Active" },
    { id: 5, name: "Beverages", icon: "bi-cup-hot", description: "Tea, coffee and cold drinks", status: "Active" },
  ],

  foodItems: [
    { id: 1, name: "Masala Dosa", categoryId: 1, price: 60, emoji: "??", image: "assets/images/food/masala-dosa.jpg", popular: true, status: "Available", description: "Crisp rice-lentil crepe filled with spiced potato, served with sambar and coconut chutney." },
    { id: 2, name: "Veg Sandwich", categoryId: 2, price: 40, emoji: "??", image: "assets/images/food/veg-sandwich.jpg", popular: false, status: "Available", description: "Grilled bread layered with cucumber, tomato, onion and green chutney." },
    { id: 3, name: "Cheese Burger", categoryId: 3, price: 80, emoji: "??", image: "assets/images/food/cheese-burger.jpg", popular: true, status: "Available", description: "Veg patty, melted cheese, lettuce and house sauce in a toasted bun." },
    { id: 4, name: "Paneer Roll", categoryId: 3, price: 70, emoji: "??", image: "assets/images/food/paneer-roll.jpg", popular: true, status: "Available", description: "Tandoori paneer tikka, onions and mint mayo wrapped in a soft roomali roti." },
    { id: 5, name: "Veg Pizza", categoryId: 3, price: 120, emoji: "??", image: "assets/images/food/veg-pizza.jpg", popular: false, status: "Available", description: "Thin-crust pizza with capsicum, corn, olives and mozzarella." },
    { id: 6, name: "Cold Coffee", categoryId: 5, price: 50, emoji: "??", image: "assets/images/food/cold-coffee.jpg", popular: true, status: "Available", description: "Chilled blended coffee with milk." },
    { id: 7, name: "Tea", categoryId: 5, price: 15, emoji: "?", image: "assets/images/food/tea.jpg", popular: false, status: "Available", description: "Fresh cutting chai brewed with ginger and cardamom." },
    { id: 8, name: "Samosa", categoryId: 2, price: 15, emoji: "??", image: "assets/images/food/samosa.jpg", popular: true, status: "Available", description: "Golden fried pastry stuffed with spiced potato and peas." },
    { id: 9, name: "French Fries", categoryId: 2, price: 50, emoji: "??", image: "assets/images/food/french-fries.jpg", popular: false, status: "Available", description: "Crispy salted fries with peri-peri seasoning." },
    { id: 10, name: "Veg Biryani", categoryId: 4, price: 90, emoji: "??", image: "assets/images/food/veg-biryani.jpg", popular: true, status: "Available", description: "Fragrant basmati rice cooked with vegetables and whole spices, served with raita." },
    { id: 11, name: "Veg Thali", categoryId: 4, price: 110, emoji: "??", image: "assets/images/food/veg-thali.jpg", popular: false, status: "Unavailable", description: "Two sabzis, dal, rice, roti, salad and sweet." },
  ],

  users: [
    { id: 1, name: "Aarav Sharma", email: "aarav@student.pccoe.org", phone: "9876543210", password: "student123", registeredOn: daysAgo(40), status: "Active" },
    { id: 2, name: "Riya Patil", email: "riya@student.pccoe.org", phone: "9823012345", password: "student123", registeredOn: daysAgo(31), status: "Active" },
    { id: 3, name: "Kunal Deshmukh", email: "kunal@student.pccoe.org", phone: "9922334455", password: "student123", registeredOn: daysAgo(22), status: "Active" },
    { id: 4, name: "Sneha Joshi", email: "sneha@student.pccoe.org", phone: "9765432109", password: "student123", registeredOn: daysAgo(15), status: "Active" },
    { id: 5, name: "Rohan Kulkarni", email: "rohan@student.pccoe.org", phone: "9890123456", password: "student123", registeredOn: daysAgo(9), status: "Blocked" },
    { id: 6, name: "Demo Student", email: "student@cafeteria.com", phone: "9000000001", password: "student123", registeredOn: daysAgo(3), status: "Active" },
  ],

  orders: [
    { id: "ORD-1001", userId: 1, customerName: "Aarav Sharma", phone: "9876543210", items: [{ id: 1, name: "Masala Dosa", price: 60, qty: 2 }, { id: 7, name: "Tea", price: 15, qty: 2 }], total: 150, paymentMethod: "UPI", paymentStatus: "Paid", status: "Completed", date: daysAgo(6, 9, 15) },
    { id: "ORD-1002", userId: 2, customerName: "Riya Patil", phone: "9823012345", items: [{ id: 10, name: "Veg Biryani", price: 90, qty: 1 }, { id: 6, name: "Cold Coffee", price: 50, qty: 1 }], total: 140, paymentMethod: "Card", paymentStatus: "Paid", status: "Completed", date: daysAgo(5, 13, 5) },
    { id: "ORD-1003", userId: 3, customerName: "Kunal Deshmukh", phone: "9922334455", items: [{ id: 3, name: "Cheese Burger", price: 80, qty: 2 }, { id: 9, name: "French Fries", price: 50, qty: 1 }], total: 210, paymentMethod: "UPI", paymentStatus: "Paid", status: "Completed", date: daysAgo(4, 14, 40) },
    { id: "ORD-1004", userId: 1, customerName: "Aarav Sharma", phone: "9876543210", items: [{ id: 8, name: "Samosa", price: 15, qty: 4 }, { id: 7, name: "Tea", price: 15, qty: 2 }], total: 90, paymentMethod: "Wallet", paymentStatus: "Paid", status: "Completed", date: daysAgo(3, 11, 20) },
    { id: "ORD-1005", userId: 4, customerName: "Sneha Joshi", phone: "9765432109", items: [{ id: 4, name: "Paneer Roll", price: 70, qty: 2 }, { id: 6, name: "Cold Coffee", price: 50, qty: 2 }], total: 240, paymentMethod: "UPI", paymentStatus: "Paid", status: "Completed", date: daysAgo(2, 12, 30) },
    { id: "ORD-1006", userId: 2, customerName: "Riya Patil", phone: "9823012345", items: [{ id: 5, name: "Veg Pizza", price: 120, qty: 1 }, { id: 9, name: "French Fries", price: 50, qty: 1 }], total: 170, paymentMethod: "Card", paymentStatus: "Paid", status: "Ready", date: daysAgo(1, 16, 10) },
    { id: "ORD-1007", userId: 6, customerName: "Demo Student", phone: "9000000001", items: [{ id: 1, name: "Masala Dosa", price: 60, qty: 1 }, { id: 6, name: "Cold Coffee", price: 50, qty: 1 }], total: 110, paymentMethod: "UPI", paymentStatus: "Paid", status: "Preparing", date: daysAgo(0, 9, 45) },
    { id: "ORD-1008", userId: 3, customerName: "Kunal Deshmukh", phone: "9922334455", items: [{ id: 10, name: "Veg Biryani", price: 90, qty: 2 }], total: 180, paymentMethod: "UPI", paymentStatus: "Paid", status: "Pending", date: daysAgo(0, 10, 55) },
    { id: "ORD-1009", userId: 4, customerName: "Sneha Joshi", phone: "9765432109", items: [{ id: 2, name: "Veg Sandwich", price: 40, qty: 2 }, { id: 8, name: "Samosa", price: 15, qty: 2 }], total: 110, paymentMethod: "Wallet", paymentStatus: "Paid", status: "Pending", date: daysAgo(0, 11, 30) },
  ],

  feedback: [
    { id: 1, userId: 1, name: "Aarav Sharma", rating: 5, message: "Ordering ahead saved me a 20-minute queue.", date: daysAgo(5) },
    { id: 2, userId: 2, name: "Riya Patil", rating: 4, message: "Great menu and easy checkout.", date: daysAgo(3) },
    { id: 3, userId: 4, name: "Sneha Joshi", rating: 5, message: "Cold coffee is the best on campus.", date: daysAgo(1) },
  ],
};

const ORDER_STATUSES = ["Pending", "Preparing", "Ready", "Completed", "Cancelled"];
