// Références DOM mises à jour à chaque navigation Turbo
let cartBtn, cartPanel, overlay, closeCart;
let imgoverlay, overlayImg;
let mainNavs, mainNavsW;

// Échappe les caractères HTML pour éviter les injections XSS
function esc(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// ─── Panier ──────────────────────────────────────────────────────────────────

function openPanier() {
  if (!cartPanel || !overlay) return;
  cartPanel.classList.add("active");
  overlay.classList.add("active");
}

function closePanier() {
  if (!cartPanel || !overlay) return;
  cartPanel.classList.remove("active");
  overlay.classList.remove("active");
}

function renderCart(cart) {
  if (!cartPanel) return;
  const content = cartPanel.querySelector(".cart-content");
  if (!content) return;

  if (!cart.items || cart.items.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'cart-empty';
    empty.innerHTML = '<img src="/img/cart-icon.png" alt="Panier vide"><p>Votre panier est vide</p><a href="/shop" class="cart-btn">EXPLORER NOS PRODUITS</a>';
    content.replaceChildren(empty);
    return;
  }

  const wrapper = document.createElement('div');

  const itemsDiv = document.createElement('div');
  itemsDiv.className = 'cart-items';

  cart.items.forEach(item => {
    const div = document.createElement('div');
    div.className = 'cart-item';

    const img = document.createElement('img');
    img.src = '/' + esc(item.image);
    img.alt = esc(item.name);
    img.onerror = () => { img.src = '/img/placeholder.jpg'; };
    img.style.cssText = 'width:60px;height:60px;object-fit:cover;border-radius:6px;';

    const info = document.createElement('div');
    info.className = 'cart-item-info';
    const h4 = document.createElement('h4');
    h4.textContent = item.name;
    const p = document.createElement('p');
    p.textContent = parseFloat(item.price).toFixed(2).replace('.', ',') + ' €';
    if (item.quantity > 1) {
      const qty = document.createElement('span');
      qty.className = 'cart-item-qty';
      qty.textContent = '× ' + item.quantity;
      p.appendChild(qty);
    }
    info.append(h4, p);

    const btn = document.createElement('button');
    btn.className = 'remove-item';
    btn.dataset.id = item.id;
    btn.textContent = '✕';
    btn.addEventListener('click', () => removeFromCart(item.id));

    div.append(img, info, btn);
    itemsDiv.appendChild(div);
  });

  const totalDiv = document.createElement('div');
  totalDiv.className = 'cart-total';
  totalDiv.innerHTML = '<span>Total:</span>';
  const totalAmt = document.createElement('span');
  totalAmt.textContent = parseFloat(cart.total).toFixed(2).replace('.', ',') + ' €';
  totalDiv.appendChild(totalAmt);

  const link = document.createElement('a');
  link.href = '/cart/checkout';
  link.className = 'cart-btn';
  link.textContent = 'COMMANDER';

  wrapper.append(itemsDiv, totalDiv, link);
  content.replaceChildren(wrapper);
}

function addToCart(url) {
  fetch(url, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
    .then(r => r.json())
    .then(cart => { renderCart(cart); openPanier(); })
    .catch(err => console.error('Cart error:', err));
}

function removeFromCart(id) {
  fetch('/cart/remove/' + id, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  })
    .then(r => r.json())
    .then(cart => renderCart(cart))
    .catch(() => window.location.reload());
}

// ─── Masonry ─────────────────────────────────────────────────────────────────

function resizeMasonryItem(item) {
  const grid = document.querySelector('.masonry');
  if (!grid) return;
  const rowHeight = parseInt(window.getComputedStyle(grid).getPropertyValue('grid-auto-rows'));
  const rowGap    = parseInt(window.getComputedStyle(grid).getPropertyValue('gap'));
  const img       = item.querySelector('img');
  if (!img) return;
  const rowSpan = Math.ceil((img.getBoundingClientRect().height + rowGap) / (rowHeight + rowGap));
  item.style.gridRowEnd = 'span ' + rowSpan;
}

function resizeAllMasonryItems() {
  document.querySelectorAll('.masonry .item').forEach(item => resizeMasonryItem(item));
}

// ─── Nav gradient ─────────────────────────────────────────────────────────────

function updateNavGradients() {
  const scrolled = window.scrollY > 10;
  mainNavs  && mainNavs.forEach(nav  => nav.classList.toggle("has-gradient", scrolled));
  mainNavsW && mainNavsW.forEach(nav => nav.classList.toggle("has-gradient", scrolled));
}

// ─── Init (exécuté au chargement initial ET après chaque navigation Turbo) ───

function init() {
  // Panier
  cartBtn   = document.getElementById("cart-btn");
  cartPanel = document.getElementById("cart-panel");
  overlay   = document.getElementById("cart-overlay");
  closeCart = document.getElementById("close-cart");

  if (cartPanel && overlay && closeCart) {
    if (cartBtn) cartBtn.addEventListener("click", openPanier);
    closeCart.addEventListener("click", closePanier);
    overlay.addEventListener("click", closePanier);
  }

  // Image overlay
  imgoverlay = document.getElementById("image-overlay");
  overlayImg = document.getElementById("overlay-img");

  if (imgoverlay) {
    imgoverlay.addEventListener("click", () => {
      imgoverlay.classList.remove("active");
      overlayImg.src = "";
      document.body.style.overflow = "";
    });
  }

  // Nav gradient
  mainNavs  = document.querySelectorAll(".main-nav");
  mainNavsW = document.querySelectorAll(".main-nav-w");
  updateNavGradients();

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

  // Masonry + animations (attend le chargement de chaque image)
  const masonryItems = document.querySelectorAll('.masonry .item');
  if (masonryItems.length > 0) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('show'); });
    }, { threshold: 0.1 });

    masonryItems.forEach(item => {
      observer.observe(item);
      const img = item.querySelector('img');
      if (!img) return;
      if (img.complete) {
        resizeMasonryItem(item);
      } else {
        img.addEventListener('load', () => resizeMasonryItem(item));
      }
    });
  }
}

// ─── Listeners globaux (une seule fois, survivent aux navigations Turbo) ─────

document.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-add-cart]");
  if (btn) { e.preventDefault(); addToCart(btn.dataset.addCart); }
});

document.addEventListener("click", (e) => {
  const img = e.target.closest(".item img");
  if (img && imgoverlay && overlayImg) {
    overlayImg.src = img.src;
    imgoverlay.classList.add("active");
    document.body.style.overflow = "hidden";
  }
});

window.addEventListener("scroll", updateNavGradients);
window.addEventListener("resize", resizeAllMasonryItems);

document.addEventListener("turbo:load", init);
