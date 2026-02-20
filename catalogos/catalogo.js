document.addEventListener('DOMContentLoaded', function () {

    const ADMIN_WHATSAPP = "584123368325"; // CAMBIA ESTO
    const grid = document.getElementById('products-grid');
    const filterBtns = document.querySelectorAll('.filter-btn');
    let allProducts = [];

    console.log("1. Iniciando script de catálogo...");

    // 1. Cargar Productos
    fetch('../admin/api.php?action=get_catalogo')
        .then(res => {
            console.log("2. Respuesta API recibida:", res);
            if (!res.ok) throw new Error("Error en la red");
            return res.json();
        })
        .then(data => {
            console.log("3. Datos JSON recibidos:", data);

            if (!data || data.length === 0) {
                console.warn("⚠️ La API devolvió una lista vacía.");
                grid.innerHTML = '<p class="loading-spinner">No hay productos registrados en la base de datos.</p>';
                return;
            }

            allProducts = data;
            renderProducts(data);
        })
        .catch(err => {
            console.error("❌ ERROR GRAVE:", err);
            grid.innerHTML = '<p class="loading-spinner">Error al conectar con el sistema.</p>';
        });

    // 2. Lógica de Filtrado
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const category = btn.getAttribute('data-cat');

            if (category === 'all') {
                renderProducts(allProducts);
            } else {
                const filtered = allProducts.filter(p => p.categoria === category);
                renderProducts(filtered);
            }
        });
    });

    // 3. Renderizar Tarjetas
    function renderProducts(products) {
        console.log(`4. Renderizando ${products.length} productos...`);
        grid.innerHTML = '';

        products.forEach(prod => {
            // LOG PARA VER QUÉ IMAGEN ESTÁ INTENTANDO CARGAR
            const logoUrl = getLogo(prod.nombre, prod.categoria);
            console.log(`   - Producto: ${prod.nombre} | URL Imagen: ${logoUrl}`);

            const isCombo = prod.categoria === 'Combo' ? 'is-combo' : '';
            const checkoutLink = `../checkout/checkout.html?id=${prod.id}&name=${encodeURIComponent(prod.nombre)}&price=${prod.precio}`;

            const html = `
                <div class="product-card ${isCombo}">
                    <img src="${logoUrl}" alt="${prod.nombre}" class="prod-icon" onerror="this.src='img/default.png'">
                    <h3 class="prod-title">${prod.nombre}</h3>
                    <p class="prod-desc">${prod.descripcion || 'Entretenimiento premium garantizado.'}</p>
                    <div class="prod-price">$${prod.precio}</div>
                    <a href="${checkoutLink}" class="prod-btn">
                        Comprar Ahora
                    </a>
                </div>
            `;
            grid.innerHTML += html;
        });
    }

    // 4. Función Auxiliar para detectar Logos
    function getLogo(name, category) {
        if (!name) return 'img/default.png';
        const n = name.toLowerCase();
        const path = '../img/'; // Asegúrate de que esta carpeta exista

        // combos

        // Combo Estudiantil 

        if (category.includes("Combo") && n.includes('youcan')) {
            return path + 'youcan.png';
        }

        // Combo Streaming

        if (category.includes("Combo") && n.includes('crunchynet')) {
            return path + 'crunchynet.png';
        }
        if (category.includes("Combo") && n.includes('crunchymax')) {
            return path + 'crunchymax.png';
        }
        if (category.includes("Combo") && n.includes('primemount+')) {
            return path + 'primemount.png';
        }
        if (category.includes("Combo") && n.includes('netmax')) {
            return path + 'netmax.png';
        }
        if (category.includes("Combo") && n.includes('disflix')) {
            return path + 'disflix.png';
        }
        if (category.includes("Combo") && n.includes('disneymax')) {
            return path + 'disneymax.png';
        }

        // Streaming
        if (n.includes('netflix')) return path + 'netflix.png';
        if (n.includes('disney')) return path + 'disney.png';
        if (n.includes('hbo')) return path + 'hbo.png';
        if (n.includes('amazon') || n.includes('primevideo')) return path + 'prime.png';
        if (n.includes('crunchyroll')) return path + 'crunchyroll.png';
        if (n.includes('paramount')) return path + 'paramount.png';
        if (n.includes('vix')) return path + 'vix.png';
        if (n.includes('plex')) return path + 'plex.png';
        if (n.includes('viki')) return path + 'viki.png';

        // Estudio 
        if (n.includes('canva')) return path + 'canva.png';
        if (n.includes('gemini')) return path + 'gemini.png';
        if (n.includes('chat gpt')) return path + 'gpt.png';
        if (n.includes('capcut')) return path + 'capcut.png';
        if (n.includes('claude')) return path + 'claude.png';
        if (n.includes('sora')) return path + 'sora.png';
        if (n.includes('midjourney')) return path + 'mid.png';


        // Musica
        if (n.includes('youtube')) return path + 'youtube.png';
        if (n.includes('spotify')) return path + 'spotify.png';
        if (n.includes('deezer')) return path + 'deezer.png';
        if (n.includes('soundcloud')) return path + 'soundcloud.png';
        if (n.includes('qobuz')) return path + 'qobuz.png';

        // Gaming
        if (n.includes('deluxe')) return path + 'psplus.png';
        if (n.includes('xbox')) return path + 'xbox.png';
        if (n.includes('discord')) return path + 'discord.png';

        // Otros
        if (n.includes('one')) return path + 'one.png';
        if (n.includes('windows')) return path + 'windows.png';

        return path + 'default.png';
    }

    // Al final de catalogo.js, dentro del evento DOMContentLoaded

    const floatBtn = document.getElementById('btn-whatsapp-float');
    if (floatBtn) {
        // Reemplaza con tu número. Recuerda: Sin + y con el código de país (ej: 58 para Venezuela)
        const MI_NUMERO = "584123368325";
        const mensaje = "";

        floatBtn.href = `https://wa.me/${MI_NUMERO}?text=${encodeURIComponent(mensaje)}`;
    }
});