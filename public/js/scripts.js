const cartBtn = document.getElementById("cart-btn");
const cartPanel = document.getElementById("cart-panel");
const overlay = document.getElementById("cart-overlay");
const closeCart = document.getElementById("close-cart");

function openPanier() {
  cartPanel.classList.add("active");
  overlay.classList.add("active");
}

function closePanier() {
  cartPanel.classList.remove("active");
  overlay.classList.remove("active");
}

// Rendu du panier à partir des données JSON
function renderCart(cart) {
  const content = cartPanel.querySelector(".cart-content");
  if (!content) return;

  if (!cart.items || cart.items.length === 0) {
    content.innerHTML = `
      <div class="cart-empty">
        <img src="/img/cart-icon.png" alt="Panier vide">
        <p>Votre panier est vide</p>
        <a href="/shop" class="cart-btn">EXPLORER NOS PRODUITS</a>
      </div>`;
    return;
  }

  const itemsHtml = cart.items.map(item => `
    <div class="cart-item">
      <img src="/${item.image}" onerror="this.src='/img/placeholder.jpg'" alt="${item.name}" style="width:60px;height:60px;object-fit:cover;border-radius:6px;">
      <div class="cart-item-info">
        <h4>${item.name}</h4>
        <p>${parseFloat(item.price).toFixed(2).replace('.', ',')} €${item.quantity > 1 ? ` <span class="cart-item-qty">× ${item.quantity}</span>` : ''}</p>
      </div>
      <button class="remove-item" data-id="${item.id}" onclick="removeFromCart(${item.id})">✕</button>
    </div>
  `).join('');

  const total = parseFloat(cart.total).toFixed(2).replace('.', ',');

  content.innerHTML = `
    <div class="cart-items">${itemsHtml}</div>
    <div class="cart-total">
      <span>Total:</span>
      <span>${total} €</span>
    </div>
    <a href="/user/checkout" class="cart-btn">COMMANDER</a>`;
}

// Ajouter au panier via AJAX
function addToCart(url) {
  fetch(url, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
    .then(r => {
      console.log('Status:', r.status, 'Content-Type:', r.headers.get('content-type'));
      return r.text();
    })
    .then(text => {
      console.log('Raw response:', text);
      const cart = JSON.parse(text);
      console.log('Cart data:', cart);
      renderCart(cart);
      openPanier();
    })
    .catch(err => {
      console.error('Cart error:', err);
    });
}

// Retirer du panier via AJAX
function removeFromCart(id) {
  fetch(`/user/cart/remove/${id}`, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  })
    .then(r => r.json())
    .then(cart => renderCart(cart))
    .catch(() => window.location.reload());
}

if (cartPanel && overlay && closeCart) {
  if (cartBtn) cartBtn.addEventListener("click", openPanier);
  closeCart.addEventListener("click", closePanier);
  overlay.addEventListener("click", closePanier);
}

// Bouton "Ajouter au panier" sur la page achat
document.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-add-cart]");
  if (btn) {
    e.preventDefault();
    addToCart(btn.dataset.addCart);
  }
});

// Dégradé sur les headers avec .main-nav (blanc) et .main-nav-w (bleu) au scroll
const mainNavs = document.querySelectorAll(".main-nav");
const mainNavsW = document.querySelectorAll(".main-nav-w");

function updateNavGradients() {
  const scrolled = window.scrollY > 10;

  mainNavs.forEach((nav) => {
    if (scrolled) {
      nav.classList.add("has-gradient");
    } else {
      nav.classList.remove("has-gradient");
    }
  });

  mainNavsW.forEach((nav) => {
    if (scrolled) {
      nav.classList.add("has-gradient");
    } else {
      nav.classList.remove("has-gradient");
    }
  });
}

if (mainNavs.length > 0 || mainNavsW.length > 0) {
  window.addEventListener("scroll", updateNavGradients);
  updateNavGradients();
}

function resizeMasonryItem(item) {
  const grid = document.querySelector('.masonry');
  const rowHeight = parseInt(window.getComputedStyle(grid).getPropertyValue('grid-auto-rows'));
  const rowGap = parseInt(window.getComputedStyle(grid).getPropertyValue('gap'));

  const img = item.querySelector('img');
  const itemHeight = img.getBoundingClientRect().height;

  const rowSpan = Math.ceil((itemHeight + rowGap) / (rowHeight + rowGap));
  item.style.gridRowEnd = `span ${rowSpan}`;
}

function resizeAllMasonryItems() {
  document.querySelectorAll('.item').forEach(item => resizeMasonryItem(item));
}

window.addEventListener('load', () => {
  resizeAllMasonryItems();

  // animation
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('show');
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.item').forEach(item => observer.observe(item));
});

window.addEventListener('resize', resizeAllMasonryItems);

// Filtres galerie
document.querySelectorAll('.category').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.category').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const selected = btn.dataset.category;
    document.querySelectorAll('.masonry .item').forEach(item => {
      item.style.display = (selected === 'all' || item.dataset.category === selected) ? '' : 'none';
    });

    resizeAllMasonryItems();
  });
});

const imgoverlay = document.getElementById("image-overlay");
const overlayImg = document.getElementById("overlay-img");

/* ouvrir image */
document.addEventListener("click", (e) => {
  const img = e.target.closest(".item img");

  if (img && imgoverlay && overlayImg) {
    overlayImg.src = img.src;
    imgoverlay.classList.add("active");
    document.body.style.overflow = "hidden";
  }
});

/* fermer image */
if (imgoverlay) {
  imgoverlay.addEventListener("click", () => {
    imgoverlay.classList.remove("active");
    overlayImg.src = "";
    document.body.style.overflow = "";
  });
}
