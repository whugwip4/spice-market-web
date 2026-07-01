(function () {
  const STORAGE_KEY = "spice_market_cart";

  function readCart() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEY) || "{}");
    } catch (error) {
      return {};
    }
  }

  function writeCart(cart) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
    updateCartCount();
  }

  function cartCount(cart) {
    return Object.values(cart).reduce((sum, value) => sum + Number(value || 0), 0);
  }

  function updateCartCount() {
    const cart = readCart();
    document.querySelectorAll("[data-cart-count]").forEach((node) => {
      node.textContent = String(cartCount(cart));
    });
  }

  function addToCart(slug) {
    const cart = readCart();
    cart[slug] = Number(cart[slug] || 0) + 1;
    writeCart(cart);
  }

  function removeFromCart(slug) {
    const cart = readCart();
    delete cart[slug];
    writeCart(cart);
    renderCart();
    renderCheckoutSummary();
  }

  function changeQty(slug, delta) {
    const cart = readCart();
    const next = Number(cart[slug] || 0) + delta;
    if (next <= 0) {
      delete cart[slug];
    } else {
      cart[slug] = next;
    }
    writeCart(cart);
    renderCart();
    renderCheckoutSummary();
  }

  async function fetchProducts(cart) {
    const slugs = Object.keys(cart);
    if (!slugs.length) {
      return [];
    }
    const response = await fetch(`/api/cart-products.php?slugs=${encodeURIComponent(slugs.join(","))}`);
    if (!response.ok) {
      return [];
    }
    return response.json();
  }

  function productVisual(product) {
    const src = product.image_src || "https://upload.wikimedia.org/wikipedia/commons/thumb/5/5c/Paprika-spice.jpg/960px-Paprika-spice.jpg";
    return `
      <div class="product-photo">
        <img src="${src}" alt="${product.name}" loading="lazy" onerror="this.closest('.product-photo').classList.add('image-failed'); this.remove();">
      </div>
    `;
  }

  function money(value) {
    return `${Math.round(Number(value || 0)).toLocaleString("ru-RU")} ₽`;
  }

  async function renderCart() {
    const mount = document.querySelector("[data-cart-list]");
    const totalNode = document.querySelector("[data-cart-total]");
    const countNode = document.querySelector("[data-cart-items-count]");
    if (!mount) {
      return;
    }

    const cart = readCart();
    const products = await fetchProducts(cart);

    if (!products.length) {
      mount.innerHTML = '<div class="empty-state">Корзина пуста. Перейдите в каталог и добавьте товары.</div>';
      if (totalNode) totalNode.textContent = money(0);
      if (countNode) countNode.textContent = "0";
      return;
    }

    let total = 0;
    let count = 0;
    mount.innerHTML = products.map((product) => {
      const qty = Number(cart[product.slug] || 1);
      const line = Number(product.price) * qty;
      total += line;
      count += qty;
      return `
        <article class="cart-row">
          ${productVisual(product)}
          <div>
            <h3>${product.name}</h3>
            <p>${product.weight_grams} г · ${product.category_name || "специи"}</p>
          </div>
          <div class="qty-control">
            <button class="icon-btn" type="button" data-cart-dec="${product.slug}">-</button>
            <strong>${qty}</strong>
            <button class="icon-btn" type="button" data-cart-inc="${product.slug}">+</button>
          </div>
          <strong>${money(line)}</strong>
          <button class="icon-btn" type="button" data-cart-remove="${product.slug}" aria-label="Удалить">×</button>
        </article>
      `;
    }).join("");

    if (totalNode) totalNode.textContent = money(total);
    if (countNode) countNode.textContent = String(count);
  }

  async function renderCheckoutSummary() {
    const mount = document.querySelector("[data-checkout-summary]");
    const payload = document.querySelector("[name='cart_payload']");
    if (!mount && !payload) {
      return;
    }

    const cart = readCart();
    if (payload) {
      payload.value = JSON.stringify(cart);
    }

    const products = await fetchProducts(cart);
    if (!mount) {
      return;
    }

    if (!products.length) {
      mount.innerHTML = '<p class="empty-state">Корзина пуста.</p>';
      return;
    }

    let total = 0;
    const lines = products.map((product) => {
      const qty = Number(cart[product.slug] || 1);
      const line = Number(product.price) * qty;
      total += line;
      return `<div class="summary-line"><span>${product.name} × ${qty}</span><strong>${money(line)}</strong></div>`;
    });

    mount.innerHTML = `${lines.join("")}<div class="summary-line total"><span>Итого</span><strong>${money(total)}</strong></div>`;
  }

  document.addEventListener("click", (event) => {
    const addButton = event.target.closest(".js-add-cart");
    if (addButton) {
      addToCart(addButton.dataset.slug);
      addButton.textContent = "Добавлено";
      setTimeout(() => {
        addButton.textContent = "В корзину";
      }, 900);
      return;
    }

    const inc = event.target.closest("[data-cart-inc]");
    if (inc) {
      changeQty(inc.dataset.cartInc, 1);
      return;
    }

    const dec = event.target.closest("[data-cart-dec]");
    if (dec) {
      changeQty(dec.dataset.cartDec, -1);
      return;
    }

    const remove = event.target.closest("[data-cart-remove]");
    if (remove) {
      removeFromCart(remove.dataset.cartRemove);
      return;
    }

    const showMore = event.target.closest("[data-show-more-products]");
    if (showMore) {
      document.querySelectorAll(".catalog-extra").forEach((card) => {
        card.classList.remove("is-hidden");
      });
      showMore.remove();
    }
  });

  updateCartCount();
  renderCart();
  renderCheckoutSummary();
})();
