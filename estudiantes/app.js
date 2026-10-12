(() => {
  "use strict";

  // La aplicación debe ejecutarse mediante HTTP/HTTPS, no con file://.
  // Si el frontend está servido por el mismo Apache que la API, las rutas
  // /api/... se resuelven contra el mismo origen automáticamente.
  const IS_FILE_PROTOCOL = window.location.protocol === "file:";
  const API_ROOT = IS_FILE_PROTOCOL ? "http://localhost" : window.location.origin;

  const state = {
    referenceDate: new Date(),
    schedule: "Todos",
    selectedDay: "Todos",
    search: "",
    category: "Todos",
    sort: "default",
    loadedMenus: []
  };

  const $ = (selector) => document.querySelector(selector);
  const $$ = (selector) => [...document.querySelectorAll(selector)];

  document.addEventListener("DOMContentLoaded", init);

  function init() {
    bindTabs();
    bindControls();

    if (IS_FILE_PROTOCOL) {
      mostrarErrorProtocoloArchivo();
      return;
    }

    loadAll();
  }

  function bindTabs() {
    $$(".tab").forEach(button => {
      button.addEventListener("click", () => {
        $$(".tab").forEach(tab => tab.classList.remove("active"));
        $$(".panel").forEach(panel => panel.classList.remove("active"));
        button.classList.add("active");
        $(`#panel-${button.dataset.tab}`).classList.add("active");
      });
    });
  }

  function bindControls() {
    $("#scheduleFilter").addEventListener("change", event => {
      state.schedule = event.target.value;
      loadMenu();
    });

    $("#noticeFilter").addEventListener("change", loadNotices);

    $("#prevWeek").addEventListener("click", () => {
      state.referenceDate = addDays(state.referenceDate, -7);
      loadMenu();
    });

    $("#nextWeek").addEventListener("click", () => {
      state.referenceDate = addDays(state.referenceDate, 7);
      loadMenu();
    });

    $("#todayWeek").addEventListener("click", () => {
      state.referenceDate = new Date();
      loadMenu();
    });

    $("#refreshAll").addEventListener("click", loadAll);

    $("#menuSearch").addEventListener("input", event => {
      state.search = event.target.value.trim().toLowerCase();
      renderFilteredMenu();
    });

    $("#categoryFilter").addEventListener("change", event => {
      state.category = event.target.value;
      renderFilteredMenu();
    });

    $("#sortFilter").addEventListener("change", event => {
      state.sort = event.target.value;
      renderFilteredMenu();
    });

    $("#clearFilters").addEventListener("click", () => {
      state.selectedDay = "Todos";
      state.search = "";
      state.category = "Todos";
      state.sort = "default";

      $("#menuSearch").value = "";
      $("#categoryFilter").value = "Todos";
      $("#sortFilter").value = "default";

      $$(".day-chip").forEach(chip => {
        chip.classList.toggle("active", chip.dataset.day === "Todos");
      });

      renderFilteredMenu();
    });

    $("#dayFilter").addEventListener("click", event => {
      const chip = event.target.closest(".day-chip");
      if (!chip) return;

      state.selectedDay = chip.dataset.day;

      $$(".day-chip").forEach(item => {
        item.classList.toggle("active", item === chip);
      });

      renderFilteredMenu();
    });

    document.addEventListener("click", event => {
      const details = event.target.closest("[data-product-details]");
      if (details) {
        try {
          showProductModal(JSON.parse(decodeURIComponent(details.dataset.productDetails)));
        } catch (error) {
          console.error(error);
        }
      }

      if (event.target.closest("[data-close-modal]")) {
        closeModal();
      }
    });

    document.addEventListener("keydown", event => {
      if (event.key === "Escape") closeModal();
    });
  }

  function mostrarErrorProtocoloArchivo() {
    const mensaje =
      "Esta página se abrió como archivo local (file://). " +
      "Para consultar la API debes abrirla desde Apache, por ejemplo: " +
      "http://localhost/cafi_menu_web/";

    const menuStatus = $("#menuStatus");
    const noticeStatus = $("#noticeStatus");
    const specialStatus = $("#specialStatus");

    [menuStatus, noticeStatus, specialStatus].forEach(status => {
      if (!status) return;
      status.className = "status error";
      status.textContent = mensaje;
    });

    const banner = document.createElement("div");
    banner.className = "protocol-warning";
    banner.innerHTML = `
      <strong>⚠️ Abre CAFI desde el servidor web</strong>
      <span>
        No uses doble clic sobre <code>index.html</code>.
        Coloca esta carpeta dentro de <code>htdocs</code> y entra a
        <code>http://localhost/cafi_menu_web/</code>.
      </span>
    `;

    const main = document.querySelector("main.container");
    if (main) main.prepend(banner);

    console.error(
      "[CAFI] El frontend fue abierto con file://. " +
      "Usa http://localhost/cafi_menu_web/ para evitar errores CORS."
    );
  }

  function loadAll() {
    Promise.allSettled([loadMenu(), loadNotices(), loadSpecials()]);
  }

  async function fetchJSON(path) {
    const url = `${API_ROOT}${path}`;

    let response;
    try {
      response = await fetch(url, {
      method: "GET",
      headers: { "Accept": "application/json" },
        credentials: "same-origin"
      });
    } catch (error) {
      throw new Error(`No se pudo conectar con ${url}. Verifica que Apache esté iniciado.`);
    }

    const text = await response.text();
    let data;

    try {
      data = JSON.parse(text);
    } catch {
      throw new Error(`El servidor no devolvió JSON válido (${response.status}).`);
    }

    if (!response.ok || data.success === false) {
      throw new Error(data.error || `Error HTTP ${response.status}`);
    }

    return data;
  }

  async function loadMenu() {
    const status = $("#menuStatus");
    const container = $("#menuContainer");

    status.className = "status loading";
    status.textContent = "Cargando menú...";
    container.innerHTML = "";

    const { week, year } = getISOWeekYear(state.referenceDate);
    $("#weekTitle").textContent = `📅 Semana ${week} del ${year}`;

    const params = new URLSearchParams({
      semana: String(week),
      anio: String(year),
      horario: state.schedule
    });

    try {
      const data = await fetchJSON(`/api/normaluser/menu?${params}`);
      const menus = Array.isArray(data.menus) ? data.menus : [];

      $("#weekTitle").textContent = `📅 Semana ${data.semana ?? week} del ${data.anio ?? year}`;

      if (data.fecha_inicio && data.fecha_fin) {
        $("#weekRange").textContent =
          `${formatDate(data.fecha_inicio)} — ${formatDate(data.fecha_fin)}`;
      } else {
        $("#weekRange").textContent = "";
      }

      status.className = "status success";

      if (!menus.length) {
        state.loadedMenus = [];
        updateCategoryFilter([]);
        container.appendChild(emptyNode("No hay menú disponible para esta semana."));
        return;
      }

      state.loadedMenus = menus;
      updateCategoryFilter(menus);
      renderFilteredMenu();
    } catch (error) {
      status.className = "status error";
      status.textContent = `No se pudo cargar el menú: ${error.message}`;
    }
  }

  function renderFilteredMenu() {
    const container = $("#menuContainer");
    container.innerHTML = "";

    let menus = structuredCloneSafe(state.loadedMenus);

    if (!menus.length) {
      container.appendChild(emptyNode("No hay menú disponible para esta semana."));
      return;
    }

    const search = state.search;
    const selectedDay = normalizeText(state.selectedDay);
    const selectedCategory = state.category;

    menus = menus
      .filter(menu => {
        if (state.selectedDay === "Todos") return true;

        const dayFromField = normalizeText(menu.DiaSemana || "");
        const dayFromDate = normalizeText(dayName(menu.Fecha || ""));

        return dayFromField === selectedDay || dayFromDate === selectedDay;
      })
      .map(menu => {
        const copy = { ...menu };

        const sections = Array.isArray(menu.secciones)
          ? menu.secciones
          : Array.isArray(menu.Secciones) ? menu.Secciones : [];

        copy.secciones = sections
          .map(section => {
            const sec = { ...section };

            const products = Array.isArray(section.productos)
              ? section.productos
              : Array.isArray(section.Productos) ? section.Productos : [];

            sec.productos = products.filter(product => {
              const category = product.categoria || product.Categoria || "";

              if (selectedCategory !== "Todos" && category !== selectedCategory) {
                return false;
              }

              if (!search) return true;

              const ingredients = getArray(product, "ingredientes", "Ingredientes")
                .map(i =>
                  i.nombre_ingrediente ||
                  i.Nombre ||
                  i.nombre ||
                  ""
                )
                .join(" ");

              const sizes = getArray(product, "tamanos", "Tamanos")
                .map(s => s.Nombre || s.nombre || "")
                .join(" ");

              const haystack = normalizeText([
                product.Nombre,
                product.Descripcion,
                category,
                ingredients,
                sizes,
                section.nombre_seccion,
                section.Nombre
              ].filter(Boolean).join(" "));

              return haystack.includes(normalizeText(search));
            });

            sec.productos = sortProducts(sec.productos);

            return sec;
          })
          .filter(section => section.productos.length > 0);

        return copy;
      })
      .filter(menu => menu.secciones.length > 0);

    if (!menus.length) {
      container.appendChild(
        emptyNode("No se encontraron platillos con los filtros seleccionados.")
      );
      return;
    }

    renderMenu(menus, container);
  }

  function renderMenu(menus, container) {
    const grouped = new Map();

    menus.forEach(menu => {
      const date = menu.Fecha;
      if (!grouped.has(date)) grouped.set(date, []);
      grouped.get(date).push(menu);
    });

    [...grouped.keys()].sort().forEach(date => {
      const card = document.createElement("article");
      card.className = "day-card";

      const h = document.createElement("h3");
      h.className = "day-heading";
      h.textContent = `${dayName(date)} — ${formatLongDate(date)}`;
      card.appendChild(h);

      grouped.get(date).forEach(menu => {
        card.appendChild(renderSchedule(menu));
      });

      container.appendChild(card);
    });
  }

  function updateCategoryFilter(menus) {
    const select = $("#categoryFilter");
    if (!select) return;

    const categories = new Set();

    menus.forEach(menu => {
      const sections = Array.isArray(menu.secciones)
        ? menu.secciones
        : Array.isArray(menu.Secciones) ? menu.Secciones : [];

      sections.forEach(section => {
        const products = Array.isArray(section.productos)
          ? section.productos
          : Array.isArray(section.Productos) ? section.Productos : [];

        products.forEach(product => {
          const category = product.categoria || product.Categoria;
          if (category) categories.add(category);
        });
      });
    });

    const previous = state.category;

    select.innerHTML = `<option value="Todos">Todas</option>`;

    [...categories]
      .sort((a, b) => a.localeCompare(b, "es"))
      .forEach(category => {
        const option = document.createElement("option");
        option.value = category;
        option.textContent = category;
        select.appendChild(option);
      });

    if ([...select.options].some(o => o.value === previous)) {
      select.value = previous;
    } else {
      state.category = "Todos";
      select.value = "Todos";
    }
  }

  function sortProducts(products) {
    const result = [...products];

    switch (state.sort) {
      case "price-asc":
        result.sort((a, b) => effectivePrice(a) - effectivePrice(b));
        break;
      case "price-desc":
        result.sort((a, b) => effectivePrice(b) - effectivePrice(a));
        break;
      case "calories-asc":
        result.sort((a, b) => number(a.Calorias) - number(b.Calorias));
        break;
      case "calories-desc":
        result.sort((a, b) => number(b.Calorias) - number(a.Calorias));
        break;
      case "name-asc":
        result.sort((a, b) =>
          String(a.Nombre || "").localeCompare(String(b.Nombre || ""), "es")
        );
        break;
    }

    return result;
  }




  function effectivePrice(product) {
    // En el menú semanal siempre se usa el producto normal.
    // Los precios especiales se muestran exclusivamente en la pestaña Especiales.
    return number(product.PrecioBase);
  }

  function structuredCloneSafe(value) {
    if (typeof structuredClone === "function") {
      return structuredClone(value);
    }
    return JSON.parse(JSON.stringify(value));
  }

  function normalizeText(value) {
    return String(value ?? "")
      .trim()
      .toLocaleLowerCase("es")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "");
  }

  function renderSchedule(menu) {
    const wrapper = document.createElement("section");
    wrapper.className = "schedule-section";

    const title = document.createElement("h4");
    title.className = "schedule-title";
    title.textContent =
      String(menu.Horario).toLowerCase() === "desayuno"
        ? "🥚 DESAYUNO"
        : "🍕 COMIDA";
    wrapper.appendChild(title);

    const sections = Array.isArray(menu.secciones)
      ? menu.secciones
      : Array.isArray(menu.Secciones) ? menu.Secciones : [];

    if (!sections.length) {
      wrapper.appendChild(emptyNode("No hay platillos asignados para este horario."));
      return wrapper;
    }

    sections.forEach(section => wrapper.appendChild(renderMenuSection(section)));
    return wrapper;
  }

  function renderMenuSection(section) {
    const card = document.createElement("div");
    card.className = "menu-section";

    const color = safeColor(
      section.color_seccion || section.Color || "#6c757d"
    );

    const badge = document.createElement("span");
    badge.className = "section-badge";
    badge.style.backgroundColor = color;
    badge.style.color = isDarkColor(color) ? "#fff" : "#243447";
    badge.textContent = `✔️ ${section.nombre_seccion || section.Nombre || "Sección"}`;
    card.appendChild(badge);

    const description =
      section.descripcion_seccion || section.Descripcion || "";
    if (description) {
      const p = document.createElement("p");
      p.className = "section-description";
      p.textContent = description;
      card.appendChild(p);
    }

    const products = Array.isArray(section.productos)
      ? section.productos
      : Array.isArray(section.Productos) ? section.Productos : [];

    if (!products.length) {
      card.appendChild(emptyNode("No hay productos en esta sección."));
      return card;
    }

    const grid = document.createElement("div");
    grid.className = "products-grid";
    products.forEach(product => grid.appendChild(renderProduct(product)));
    card.appendChild(grid);

    return card;
  }

  function renderProduct(product) {
    const card = document.createElement("article");
    card.className = "product-card menu-product-card";

    const photo = product.URLFoto || product.urlFoto || "";

    const media = document.createElement("div");
    media.className = "menu-product-media";

    if (photo) {
      const img = document.createElement("img");
      img.className = "menu-product-image";
      img.loading = "lazy";
      img.alt = product.Nombre || "Producto";
      img.src = imageURL(photo);

      img.addEventListener("error", () => {
        media.innerHTML = `
          <div class="menu-product-placeholder">
            <span>🍽️</span>
            <small>Imagen no disponible</small>
          </div>`;
      });

      media.appendChild(img);
    } else {
      media.innerHTML = `
        <div class="menu-product-placeholder">
          <span>🍽️</span>
          <small>Sin imagen</small>
        </div>`;
    }

    if (bool(product.Destacado)) {
      const featured = document.createElement("span");
      featured.className = "featured-badge";
      featured.textContent = "⭐ Destacado";
      media.appendChild(featured);
    }

    card.appendChild(media);

    const body = document.createElement("div");
    body.className = "menu-product-content";

    const name = document.createElement("h4");
    name.className = "menu-product-name";
    name.textContent = product.Nombre || "Producto";

    body.appendChild(name);

    const base = number(product.PrecioBase);

    const basicInfo = document.createElement("div");
    basicInfo.className = "menu-product-basic";

    const price = document.createElement("div");
    price.className = "menu-product-price";

    // Menú semanal = producto normal, sin mezclar promoción ni producto especial.
    price.innerHTML = `<strong>${money(base)}</strong>`;

    const calories = document.createElement("div");
    calories.className = "menu-product-calories";
    calories.textContent =
      number(product.Calorias) > 0
        ? `🔥 ${Math.round(number(product.Calorias))} cal`
        : "🔥 Calorías no registradas";

    basicInfo.append(price, calories);
    body.appendChild(basicInfo);

    const details = document.createElement("button");
    details.className = "details-button compact-details-button";
    details.textContent = "Ver más";
    details.dataset.productDetails =
      encodeURIComponent(JSON.stringify(product));

    body.appendChild(details);
    card.appendChild(body);

    return card;
  }

  async function loadNotices() {
    const status = $("#noticeStatus");
    const container = $("#noticeContainer");

    status.className = "status loading";
    status.textContent = "Cargando avisos...";
    container.innerHTML = "";

    const selected = $("#noticeFilter").value;
    let establishment = "Todos";
    let priority = "Todos";

    if (selected === "Cafetería") establishment = "Cafeteria";
    if (selected === "Cafecito") establishment = "Cafecito";
    if (selected === "Importantes") priority = "Importante";

    const params = new URLSearchParams({
      establecimiento: establishment,
      tipo: "Todos",
      prioridad: priority
    });

    try {
      const data = await fetchJSON(`/api/normaluser/avisos?${params}`);
      const notices = Array.isArray(data.avisos) ? data.avisos : [];
      status.className = "status success";

      if (!notices.length) {
        container.appendChild(emptyNode("No hay avisos disponibles."));
        return;
      }

      notices.forEach(notice => container.appendChild(renderNotice(notice)));
    } catch (error) {
      status.className = "status error";
      status.textContent = `No se pudieron cargar los avisos: ${error.message}`;
    }
  }

  function renderNotice(notice) {
    const card = document.createElement("article");
    card.className = "notice-card";

    let color = notice.color_borde || "#6c757d";
    if (notice.Prioridad === "Importante") color = "#dc3545";
    else if (notice.Establecimiento === "Cafeteria") color = "#007bff";
    else if (notice.Establecimiento === "Cafecito") color = "#28a745";

    card.style.setProperty("--notice-color", safeColor(color));

    const header = document.createElement("div");
    header.className = "notice-header";

    const icon = document.createElement("span");
    icon.className = "notice-icon";
    icon.textContent = notice.icono || "📢";

    const title = document.createElement("h3");
    title.className = "notice-title";
    title.textContent = notice.Titulo || "Aviso";

    const badge = document.createElement("span");
    badge.className = `badge ${notice.Prioridad === "Importante" ? "important" : ""}`;
    badge.textContent =
      notice.Prioridad === "Importante" ? "❗ IMPORTANTE" : (notice.Prioridad || "Normal");

    header.append(icon, title, badge);

    const content = document.createElement("p");
    content.className = "notice-content";
    content.textContent = notice.Contenido || "";

    const footer = document.createElement("div");
    footer.className = "notice-footer";

    footer.appendChild(textSpan(`🏢 ${displayEstablishment(notice.Establecimiento)}`));
    footer.appendChild(textSpan(`🎯 ${displayNoticeType(notice.TipoAviso)}`));

    if (notice.FechaInicio || notice.FechaFin) {
      footer.appendChild(textSpan(`📅 ${notice.FechaInicio || ""} - ${notice.FechaFin || ""}`));
    }

    if (notice.creador_nombre) {
      footer.appendChild(
        textSpan(`👤 ${notice.creador_nombre} ${notice.creador_apellido || ""}`.trim())
      );
    }

    if (notice.FechaPublicacion) {
      footer.appendChild(textSpan(`📝 ${notice.FechaPublicacion}`));
    }

    card.append(header, content, footer);
    return card;
  }

  async function loadSpecials() {
    const status = $("#specialStatus");
    const container = $("#specialContainer");

    status.className = "status loading";
    status.textContent = "Cargando promociones...";
    container.innerHTML = "";

    try {
      const data = await fetchJSON("/api/normaluser/especiales");
      const specials = Array.isArray(data.productos_especiales)
        ? data.productos_especiales
        : [];

      status.className = "status success";

      if (!specials.length) {
        container.appendChild(emptyNode("No hay productos especiales disponibles en este momento."));
        return;
      }

      specials.forEach(item => container.appendChild(renderSpecial(item)));
    } catch (error) {
      status.className = "status error";
      status.textContent = `No se pudieron cargar las promociones: ${error.message}`;
    }
  }

  function renderSpecial(item) {
    const card = document.createElement("article");
    card.className = "special-card";

    if (item.URLFoto) {
      const img = document.createElement("img");
      img.className = "special-image";
      img.alt = item.nombre_producto || "Producto especial";
      img.loading = "lazy";
      img.src = imageURL(item.URLFoto);
      img.addEventListener("error", () => img.remove());
      card.appendChild(img);
    }

    const body = document.createElement("div");
    body.className = "special-body";

    const header = document.createElement("div");
    header.className = "special-header";

    const baseForHeader = number(item.PrecioBase);
    const specialForHeader = number(item.PrecioEspecial);
    const isDiscountForHeader =
      specialForHeader > 0 &&
      baseForHeader > 0 &&
      specialForHeader < baseForHeader;

    header.innerHTML = isDiscountForHeader
      ? `<span>⭐</span><strong>OFERTA ESPECIAL</strong>`
      : `<span>⭐</span><strong>PRODUCTO ESPECIAL</strong>`;

    const discount = number(item.porcentaje_descuento);
    if (isDiscountForHeader && discount > 0) {
      const badge = document.createElement("span");
      badge.className = "discount";
      badge.textContent = `-${Math.round(discount)}%`;
      header.appendChild(badge);
    }

    const name = document.createElement("h3");
    name.className = "special-name";
    name.textContent = item.nombre_producto || "Producto especial";

    const prices = document.createElement("div");
    prices.className = "special-prices";

    const basePrice = number(item.PrecioBase);
    const specialPrice = number(item.PrecioEspecial);
    const isDiscount = specialPrice > 0 && basePrice > 0 && specialPrice < basePrice;

    if (isDiscount) {
      prices.innerHTML = `
        <span class="old">${money(basePrice)}</span>
        <span class="new">${money(specialPrice)}</span>`;
    } else {
      // Edición limitada / producto especial:
      // mostrar únicamente PrecioEspecial en rojo.
      prices.innerHTML = `
        <span class="new">${money(specialPrice)}</span>`;
    }

    body.append(header, name, prices);

    if (item.descripcion_especial) {
      const note = document.createElement("p");
      note.className = "special-note";
      note.textContent = `💡 ${item.descripcion_especial}`;
      body.appendChild(note);
    }

    if (item.descripcion_producto) {
      const description = document.createElement("p");
      description.className = "product-description";
      description.textContent = item.descripcion_producto;
      body.appendChild(description);
    }

    const meta = document.createElement("div");
    meta.className = "product-meta";

    if (item.categoria) meta.appendChild(textSpan(`📂 ${item.categoria}`));

    if (number(item.Calorias) > 0) {
      meta.appendChild(textSpan(`🔥 ${Math.round(number(item.Calorias))} cal`));
    }

    if (number(item.Gramaje) > 0) {
      meta.appendChild(textSpan(`⚖️ ${Math.round(number(item.Gramaje))} g`));
    }

    if (item.FechaFin) {
      meta.appendChild(textSpan(`⏰ Válido hasta ${item.FechaFin}`));
    }

    if (meta.childElementCount) body.appendChild(meta);

    const button = document.createElement("button");
    button.className = "details-button";
    button.textContent = "Ver detalles de la promoción";
    button.addEventListener("click", () => {
      showSpecialModal(item);
    });
    body.appendChild(button);

    card.appendChild(body);
    return card;
  }

  function showProductModal(product, useSpecialPrice = false) {
    const content = $("#modalContent");
    content.innerHTML = "";

    // ---------------------------------------------------------
    // FOTO GRANDE
    // ---------------------------------------------------------
    const photo = product.URLFoto || product.urlFoto;

    if (photo) {
      const imageWrapper = document.createElement("div");
      imageWrapper.className = "modal-image-wrapper";

      const img = document.createElement("img");
      img.className = "modal-product-image";
      img.alt = product.Nombre || "Producto";
      img.src = imageURL(photo);

      img.addEventListener("error", () => {
        imageWrapper.innerHTML = `
          <div class="modal-image-placeholder">
            <span>🍽️</span>
            <small>Imagen no disponible</small>
          </div>`;
      });

      imageWrapper.appendChild(img);
      content.appendChild(imageWrapper);
    }

    const title = document.createElement("h2");
    title.id = "modalTitle";
    title.textContent = `🍽️ ${product.Nombre || "Producto"}`;
    content.appendChild(title);

    if (product.Descripcion) {
      const p = document.createElement("p");
      p.className = "modal-description";
      p.textContent = product.Descripcion;
      content.appendChild(p);
    }

    // ---------------------------------------------------------
    // INFORMACIÓN GENERAL
    // ---------------------------------------------------------
    const base = number(product.PrecioBase);
    const special = useSpecialPrice
      ? number(product.precio_especial ?? product.PrecioEspecial)
      : 0;

    const hasSpecial = useSpecialPrice && special > 0;
    const hasDiscount =
      hasSpecial &&
      base > 0 &&
      special < base;

    const info = document.createElement("div");
    info.className = "detail-block product-main-info";

    info.innerHTML = `
      <div class="detail-row">
        📂 <strong>Categoría:</strong>
        ${escapeHTML(product.categoria || product.Categoria || "Sin categoría")}
      </div>

      <div class="detail-row">
        💰 <strong>Precio:</strong>
        ${
          hasSpecial && hasDiscount
            ? `<s>${money(base)}</s> <strong class="modal-special-price">${money(special)}</strong>`
            : hasSpecial
              ? `<strong class="modal-special-price">${money(special)}</strong>`
              : `<strong>${money(base)}</strong>`
        }
      </div>

      ${
        number(product.Calorias) > 0
          ? `<div class="detail-row">🔥 <strong>Calorías:</strong> ${Math.round(number(product.Calorias))}</div>`
          : ""
      }

      ${
        number(product.Gramaje) > 0
          ? `<div class="detail-row">⚖️ <strong>Gramaje:</strong> ${Math.round(number(product.Gramaje))} g</div>`
          : ""
      }
    `;

    content.appendChild(info);

    // ---------------------------------------------------------
    // TAMAÑOS
    // ---------------------------------------------------------
    const sizes = getArray(product, "tamanos", "Tamanos");

    if (sizes.length) {
      const block = document.createElement("div");
      block.className = "detail-block";

      const heading = document.createElement("h4");
      heading.textContent = "📏 Tamaños o presentaciones disponibles";
      block.appendChild(heading);

      const sizeGrid = document.createElement("div");
      sizeGrid.className = "modal-size-grid";

      sizes.forEach(size => {
        const sizeCard = document.createElement("div");
        sizeCard.className = "modal-size-card";

        const sizeName = document.createElement("strong");
        sizeName.textContent = size.Nombre || size.nombre || "Tamaño";

        const sizePrice = document.createElement("span");
        sizePrice.className = "modal-size-price";
        sizePrice.textContent = money(number(size.Precio ?? size.precio));

        sizeCard.append(sizeName, sizePrice);

        const details = [];
        if (size.Descripcion) details.push(size.Descripcion);
        if (number(size.Capacidad) > 0) details.push(`${Math.round(number(size.Capacidad))} ml`);
        if (number(size.Gramaje) > 0) details.push(`${Math.round(number(size.Gramaje))} g`);
        if (number(size.Piezas) > 0) details.push(`${Math.round(number(size.Piezas))} piezas`);

        if (details.length) {
          const meta = document.createElement("small");
          meta.textContent = details.join(" · ");
          sizeCard.appendChild(meta);
        }

        sizeGrid.appendChild(sizeCard);
      });

      block.appendChild(sizeGrid);
      content.appendChild(block);
    }

    // ---------------------------------------------------------
    // INGREDIENTES Y PERSONALIZACIÓN
    // ---------------------------------------------------------
    const ingredients = getArray(product, "ingredientes", "Ingredientes");

    if (ingredients.length) {
      const block = document.createElement("div");
      block.className = "detail-block";

      const heading = document.createElement("h4");
      heading.textContent = "🥗 Ingredientes y opciones de personalización";
      block.appendChild(heading);

      const legend = document.createElement("div");
      legend.className = "customization-legend";
      legend.innerHTML = `
        <span>➖ Se puede quitar</span>
        <span>🔄 Se puede sustituir</span>
        <span>⚠️ Alérgeno</span>
      `;
      block.appendChild(legend);

      const list = document.createElement("div");
      list.className = "ingredient-detail-list";

      ingredients.forEach(ingredient => {
        const item = document.createElement("article");
        item.className = "ingredient-detail-card";

        const name =
          ingredient.nombre_ingrediente ||
          ingredient.Nombre ||
          ingredient.nombre ||
          "Ingrediente";

        const top = document.createElement("div");
        top.className = "ingredient-detail-header";

        const title = document.createElement("strong");
        title.textContent = name;
        top.appendChild(title);

        const badges = document.createElement("div");
        badges.className = "ingredient-badges";

        if (bool(ingredient.Alergeno)) {
          badges.appendChild(makeBadge("⚠️ Alérgeno", "badge-allergen"));
        }

        if (bool(ingredient.Eliminable)) {
          badges.appendChild(makeBadge("➖ Se puede quitar", "badge-removable"));
        }

        if (bool(ingredient.Sustituible)) {
          badges.appendChild(makeBadge("🔄 Sustituible", "badge-substitutable"));
        }

        top.appendChild(badges);
        item.appendChild(top);

        if (ingredient.categoria_ingrediente) {
          const category = document.createElement("small");
          category.className = "ingredient-category";
          category.textContent = `Categoría: ${ingredient.categoria_ingrediente}`;
          item.appendChild(category);
        }

        const substitutes = getArray(ingredient, "sustitutos", "Sustitutos")
          .filter(sub => sub.Disponible === undefined || bool(sub.Disponible));

        if (bool(ingredient.Sustituible)) {
          const substitutions = document.createElement("div");
          substitutions.className = "substitution-box";

          const substitutionTitle = document.createElement("div");
          substitutionTitle.className = "substitution-title";
          substitutionTitle.textContent = "Puedes cambiarlo por:";
          substitutions.appendChild(substitutionTitle);

          if (substitutes.length) {
            substitutes.forEach(sub => {
              const option = document.createElement("div");
              option.className = "substitution-option";

              const subName =
                sub.nombre_sustituto ||
                sub.Nombre ||
                sub.nombre ||
                "Sustituto";

              const extra = number(sub.CostoExtra);

              option.innerHTML = `
                <span>↳ ${escapeHTML(subName)}</span>
                <span class="${extra > 0 ? "extra-cost" : "no-extra-cost"}">
                  ${extra > 0 ? `+${money(extra)}` : "Sin costo extra"}
                </span>
              `;

              substitutions.appendChild(option);
            });
          } else {
            const empty = document.createElement("div");
            empty.className = "substitution-empty";
            empty.textContent = "No hay sustitutos disponibles actualmente.";
            substitutions.appendChild(empty);
          }

          item.appendChild(substitutions);
        }

        list.appendChild(item);
      });

      block.appendChild(list);
      content.appendChild(block);
    }

    // ---------------------------------------------------------
    // RESUMEN DE MODIFICACIONES DISPONIBLES
    // ---------------------------------------------------------
    const removableCount = ingredients.filter(i => bool(i.Eliminable)).length;
    const substitutableCount = ingredients.filter(i => bool(i.Sustituible)).length;

    if (removableCount || substitutableCount) {
      const summary = document.createElement("div");
      summary.className = "customization-summary";

      summary.innerHTML = `
        <strong>🛠️ Este platillo se puede personalizar</strong>
        <span>
          ${removableCount ? `${removableCount} ingrediente${removableCount !== 1 ? "s" : ""} se puede${removableCount !== 1 ? "n" : ""} quitar.` : ""}
          ${substitutableCount ? ` ${substitutableCount} ingrediente${substitutableCount !== 1 ? "s" : ""} se puede${substitutableCount !== 1 ? "n" : ""} sustituir.` : ""}
        </span>
      `;

      content.appendChild(summary);
    }

    openModal();
  }

  function makeBadge(text, className) {
    const badge = document.createElement("span");
    badge.className = `ingredient-badge ${className}`;
    badge.textContent = text;
    return badge;
  }

  function showSpecialModal(item) {
    const product = {
      ID: item.IDProducto,
      Nombre: item.nombre_producto,
      Descripcion: item.descripcion_producto,
      PrecioBase: item.PrecioBase,
      precio_especial: item.PrecioEspecial,
      tiene_oferta: true,
      Calorias: item.Calorias,
      Gramaje: item.Gramaje,
      categoria: item.categoria,
      URLFoto: item.URLFoto
    };

    showProductModal(product, true);

    const content = $("#modalContent");
    const promo = document.createElement("div");
    promo.className = "detail-block";

    const discount = number(item.porcentaje_descuento);
    const basePrice = number(item.PrecioBase);
    const specialPrice = number(item.PrecioEspecial);
    const isDiscount = specialPrice > 0 && basePrice > 0 && specialPrice < basePrice;

    promo.innerHTML = `
      <h4>${isDiscount ? "⭐ Promoción" : "⭐ Producto especial"}</h4>
      ${item.descripcion_especial ? `<div class="detail-row">💡 ${escapeHTML(item.descripcion_especial)}</div>` : ""}
      ${isDiscount && discount > 0 ? `<div class="detail-row">🏷️ Descuento: ${Math.round(discount)}%</div>` : ""}
      ${item.FechaInicio ? `<div class="detail-row">📅 Inicio: ${escapeHTML(item.FechaInicio)}</div>` : ""}
      ${item.FechaFin ? `<div class="detail-row">⏰ Fin: ${escapeHTML(item.FechaFin)}</div>` : ""}
    `;
    content.appendChild(promo);
  }

  function openModal() {
    const modal = $("#productModal");
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeModal() {
    const modal = $("#productModal");
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  }

  function emptyNode(message) {
    const node = $("#emptyTemplate").content.firstElementChild.cloneNode(true);
    node.querySelector("p").textContent = message;
    return node;
  }

  function imageURL(path) {
    if (!path) return "";

    const raw = String(path).trim();

    if (/^https?:\/\//i.test(raw)) {
      return raw;
    }

    // Las imágenes se guardan como:
    // uploads/productos/archivo.jpg
    // Por tanto deben resolverse desde la raíz del servidor:
    // http://localhost/uploads/productos/archivo.jpg
    return `${window.location.origin}/${raw.replace(/^\/+/, "")}`;
  }

  function getArray(object, ...keys) {
    for (const key of keys) {
      if (Array.isArray(object?.[key])) return object[key];
    }
    return [];
  }

  function number(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
  }

  function bool(value) {
    return value === true || value === 1 || value === "1" || value === "true";
  }

  function money(value) {
    return new Intl.NumberFormat("es-MX", {
      style: "currency",
      currency: "MXN"
    }).format(number(value));
  }

  function textSpan(text) {
    const span = document.createElement("span");
    span.textContent = text;
    return span;
  }

  function addDays(date, amount) {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + amount);
    return copy;
  }

  function getISOWeekYear(input) {
    const date = new Date(Date.UTC(
      input.getFullYear(),
      input.getMonth(),
      input.getDate()
    ));

    const day = date.getUTCDay() || 7;
    date.setUTCDate(date.getUTCDate() + 4 - day);

    const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
    const week = Math.ceil((((date - yearStart) / 86400000) + 1) / 7);

    return { week, year: date.getUTCFullYear() };
  }

  function formatDate(isoDate) {
    const date = parseISODate(isoDate);
    return date.toLocaleDateString("es-MX", {
      day: "2-digit",
      month: "short",
      year: "numeric"
    });
  }

  function formatLongDate(isoDate) {
    const date = parseISODate(isoDate);
    return date.toLocaleDateString("es-MX", {
      day: "numeric",
      month: "long"
    });
  }

  function dayName(isoDate) {
    const date = parseISODate(isoDate);
    return date.toLocaleDateString("es-MX", { weekday: "long" }).toUpperCase();
  }

  function parseISODate(value) {
    const [year, month, day] = String(value).split("-").map(Number);
    return new Date(year, (month || 1) - 1, day || 1);
  }

  function safeColor(value) {
    return /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(value || "")
      ? value
      : "#6c757d";
  }

  function isDarkColor(hex) {
    let value = safeColor(hex).slice(1);
    if (value.length === 3) value = value.split("").map(c => c + c).join("");

    const r = parseInt(value.slice(0, 2), 16);
    const g = parseInt(value.slice(2, 4), 16);
    const b = parseInt(value.slice(4, 6), 16);
    return (0.299 * r + 0.587 * g + 0.114 * b) / 255 < .5;
  }

  function displayEstablishment(value) {
    if (value === "Cafeteria") return "Cafetería";
    if (value === "Cafecito") return "Cafecito";
    if (value === "Ambos") return "Cafetería y Cafecito";
    return value || "General";
  }

  function displayNoticeType(value) {
    const map = {
      Informativo: "Informativo",
      Promocion: "Promoción",
      Evento: "Evento",
      Horario: "Horario",
      Mantenimiento: "Mantenimiento"
    };
    return map[value] || value || "Aviso";
  }

  function escapeHTML(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }
})();
