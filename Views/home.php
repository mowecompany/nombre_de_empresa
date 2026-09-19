<?php 
    headerTienda($data);
    $arrCategorias = $data['categorias'];
?>

<style>
body {
    background:
        radial-gradient(circle at top, rgba(255,255,255,.92), transparent 28%),
        linear-gradient(180deg, #f8fbfd 0%, var(--store-bg) 46%, #edf2f7 100%);
}

.main-content-offset {
    margin-top: 128px;
}

.store-home-shell {
    max-width: none;
    margin: 0 auto;
    padding: 18px 18px 44px;
}

.home-hero {
    margin-bottom: 24px;
}

.hero-carousel-panel {
    background: rgba(255,255,255,.96);
    border: 1px solid rgba(47,74,90,.08);
    border-radius: 28px;
    box-shadow: 0 22px 46px rgba(32,56,100,.08);
    overflow: hidden;
}

#mainCarousel {
    border-radius: 28px;
    overflow: hidden;
    box-shadow: none;
    margin: 0;
    border: 0;
}

.section-title {
    text-align: left;
    color: var(--store-text);
    font-weight: 900;
    margin-bottom: 10px;
    letter-spacing: .05em;
    text-transform: uppercase;
    font-size: clamp(1.2rem, 2vw, 1.8rem);
}

.section-title::after {
    content: "";
    display: block;
    width: 74px;
    height: 4px;
    margin: 12px 0 0;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--store-primary), var(--store-secondary));
}

#mainCarousel .carousel-control-prev,
#mainCarousel .carousel-control-next {
    width: 42px !important;
    height: 42px !important;
    min-width: 42px;
    max-width: 42px;
    top: 50%;
    bottom: auto !important;
    transform: translateY(-50%);
    border-radius: 50%;
    background: rgba(255,255,255,.18);
    border: 1px solid rgba(255,255,255,.42);
    backdrop-filter: blur(10px);
    opacity: 1 !important;
    z-index: 8;
}

#mainCarousel .carousel-control-prev { left: 18px; }
#mainCarousel .carousel-control-next { right: 18px; }

#mainCarousel .carousel-control-prev-icon,
#mainCarousel .carousel-control-next-icon {
    width: 12px;
    height: 12px;
    background-size: 100% 100%;
}

#mainCarousel .carousel-control-prev:hover,
#mainCarousel .carousel-control-next:hover {
    background: rgba(255,255,255,.28);
}

.carousel-progress {
    position: absolute;
    left: 24px;
    right: 24px;
    bottom: 18px;
    height: 4px;
    background: rgba(255,255,255,.22);
    z-index: 5;
    border-radius: 999px;
    overflow: hidden;
}

.carousel-progress-bar {
    width: 0;
    height: 100%;
    background: linear-gradient(90deg, #fff, rgba(255,255,255,.82));
    transition: width 0.1s linear;
}

#mainCarousel .carousel-item {
    min-height: 390px;
    background:
        linear-gradient(125deg, color-mix(in srgb, var(--store-primary) 82%, black 18%) 0%, color-mix(in srgb, var(--store-secondary) 78%, white 22%) 100%);
}

.carousel-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 390px;
    padding: 38px 70px 42px;
    gap: 28px;
}

.carousel-text {
    color: var(--store-on-primary, #fff);
    max-width: 560px;
    padding-left: 18px;
}

.carousel-text h2 {
    font-weight: 900;
    margin-bottom: 14px;
    font-size: clamp(2rem, 4vw, 3.5rem);
    line-height: 1.05;
    letter-spacing: .03em;
}

.carousel-text p {
    margin-bottom: 22px;
    font-size: 1rem;
    line-height: 1.65;
    max-width: 48ch;
    color: rgba(255,255,255,.88);
}

.carousel-text .btn {
    border-radius: 999px;
    font-weight: 800;
    border: none;
    padding: 12px 22px;
    background: #fff;
    color: var(--store-primary);
    box-shadow: 0 16px 30px rgba(15,23,42,.18);
}

.carousel-text .btn:hover {
    transform: translateY(-1px);
}

.carousel-image {
    width: min(38%, 420px);
    min-height: 260px;
    border-radius: 26px;
    overflow: hidden;
    background: linear-gradient(180deg, rgba(255,255,255,.18), rgba(255,255,255,.1));
    border: 1px solid rgba(255,255,255,.24);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.25), 0 18px 30px rgba(15,23,42,.14);
    transform: translateX(-10px);
}

.carousel-image img {
    width: auto;
    height: auto;
    max-width: 100%;
    max-height: 280px;
    object-fit: contain;
    image-rendering: auto;
    filter: drop-shadow(0 18px 24px rgba(15,23,42,.18));
}

.section-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 24px;
}

.section-head-copy p {
    margin: 8px 0 0;
    color: var(--store-muted);
    font-size: .92rem;
    max-width: 62ch;
}

.section-head-link {
    color: var(--store-primary);
    font-weight: 800;
    text-decoration: none !important;
    white-space: nowrap;
}

.categories-section {
    padding-top: 0 !important;
    margin-bottom: 30px;
}

.categories-section .container {
    max-width: none;
    background: rgba(255,255,255,.86);
    border: 1px solid rgba(47,74,90,.08);
    box-shadow: 0 20px 42px rgba(32,56,100,.06);
    border-radius: 28px;
    padding: 22px 22px 18px;
}

.categories-carousel {
    position: relative;
    padding: 0 62px;
}

.categories-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    align-items: stretch;
}

.categories-carousel .category-nav {
    position: absolute;
    width: 38px;
    height: 38px;
    min-width: 38px;
    max-width: 38px;
    border-radius: 50%;
    background: var(--store-button, var(--store-primary));
    border: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    opacity: 0.96;
    top: 50%;
    bottom: auto;
    transform: translateY(-50%);
    z-index: 6;
    padding: 0;
    flex: 0 0 38px;
    pointer-events: auto;
    box-shadow: 0 12px 22px rgba(32,56,100,.18);
}

.categories-carousel .carousel-control-prev.category-nav,
.categories-carousel .carousel-control-next.category-nav {
    width: 38px !important;
    height: 38px !important;
    bottom: auto !important;
}

.categories-carousel .category-nav .carousel-control-prev-icon,
.categories-carousel .category-nav .carousel-control-next-icon {
    width: 12px;
    height: 12px;
    background-size: 100% 100%;
}

.categories-carousel .category-nav.carousel-control-prev { left: 4px; }
.categories-carousel .category-nav.carousel-control-next { right: 4px; }

.categories-carousel .category-nav:hover {
    background: var(--store-secondary);
}

.category-card {
    position: relative;
    border-radius: 22px;
    overflow: hidden;
    min-height: 214px;
    box-shadow: 0 14px 26px rgba(32,56,100,.08);
    margin: 6px 0;
    background: var(--store-surface);
    border: 1px solid rgba(47,74,90,.08);
    display: flex;
    flex-direction: column;
}

.category-card img {
    width: 100%;
    height: 118px;
    object-fit: contain;
    flex: 0 0 118px;
    padding: 10px 6px 10px 2px;
    background: linear-gradient(180deg, #f8fbff, #edf3f8);
    object-position: left center;
}

.category-content {
    position: static;
    inset: auto;
    background: var(--store-surface);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: flex-start;
    padding: 12px 12px 12px 18px;
    gap: 8px;
    min-height: 86px;
    border-top: 1px solid rgba(47,74,90,.08);
}

.category-content h3 {
    color: var(--store-text);
    font-size: .88rem;
    font-weight: 800;
    margin-bottom: 0;
    line-height: 1.25;
    min-height: 34px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-shadow: none;
}

.category-content .btn {
    border-radius: 999px;
    font-weight: 800;
    width: 100%;
    justify-content: center;
    padding: 9px 12px;
    font-size: .74rem;
    border: 1px solid rgba(47,74,90,.1);
    background: #fff;
    color: var(--store-text);
}

.category-content .btn:hover {
    background: var(--store-primary);
    border-color: var(--store-primary);
    color: var(--store-on-primary, #fff);
}

.products-section .container {
    max-width: none;
    background: rgba(255,255,255,.86);
    border: 1px solid rgba(47,74,90,.07);
    border-radius: 28px;
    box-shadow: 0 20px 42px rgba(32,56,100,.06);
    padding: 30px 26px;
}

.product-card {
    background: rgba(255,255,255,.92);
    border-radius: 20px;
    box-shadow: 0 16px 30px rgba(32,56,100,.09);
    padding: 0.95rem;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    transition: all 0.3s ease;
    border: 1px solid rgba(47,74,90,.08);
    position: relative;
}

.product-card.agotado {
    position: relative;
    overflow: hidden;
    border-color: rgba(239, 68, 68, .22);
}

.product-card.con-descuento {
    position: relative;
    overflow: hidden;
    border-color: rgba(74, 222, 128, .38);
    box-shadow: 0 16px 30px rgba(32,56,100,.09), 0 0 0 1px rgba(74, 222, 128, .18) inset;
}

.product-card.con-descuento:not(.agotado)::after {
    content: "";
    position: absolute;
    inset: 0;
    background: rgba(74, 222, 128, 0.08);
    pointer-events: none;
    z-index: 0;
}

.product-card.agotado::before {
    content: none;
}

@keyframes pulse {
    0% { transform: scale(1); box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3); }
    50% { transform: scale(1.05); box-shadow: 0 4px 10px rgba(220, 53, 69, 0.5); }
    100% { transform: scale(1); box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3); }
}

.product-card.agotado::after {
    content: "";
    position: absolute;
    inset: 0;
    background: rgba(220, 53, 69, 0.05);
    pointer-events: none;
    z-index: 0;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 22px 36px rgba(32,56,100,.14);
    border-color: rgba(47,74,90,.18);
}

.product-image {
    width: 100%;
    height: 178px;
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(180deg, #f8fafc, #eef3f7);
    border: 1px solid #e9ecef;
}

.product-image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    padding: 0.4rem;
}

.product-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.product-info h5 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--store-text);
    margin-bottom: 0.1rem;
    line-height: 1.24;
}

.product-category {
    font-size: 0.76rem;
    color: var(--store-muted);
    margin-bottom: 0.36rem;
    letter-spacing: .05em;
}

.product-price {
    font-size: 1.08rem;
    font-weight: 800;
    color: var(--store-text);
    margin: 0.2rem 0;
}

.product-cart-btn {
    color: var(--store-text) !important;
    border: 1px solid rgba(47,74,90,.1);
    border-radius: 999px;
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--store-surface);
}

.product-cart-btn:hover {
    color: var(--store-on-primary, var(--store-text)) !important;
    background: var(--store-button, var(--store-primary));
    border-color: var(--store-button, var(--store-primary));
}

.product-cart-btn i { color: inherit !important; }

.product-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(15,23,42,.12), rgba(15,23,42,.52));
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.3s ease;
}

.product-card:hover .product-overlay {
    opacity: 1;
}

.product-overlay .btn {
    transform: translateY(20px);
    transition: all 0.3s ease;
    border-radius: 999px;
    padding: 11px 18px;
}

.product-card:hover .product-overlay .btn {
    transform: translateY(0);
}

.featured-filters-wrap {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
    margin: 0 0 24px;
    flex-wrap: wrap;
}

.featured-filters-label {
    display: none;
}

.featured-filters-list {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 6px;
    scrollbar-width: thin;
    justify-content: flex-start;
    flex-wrap: wrap;
    width: 100%;
}

.product-status-badges {
    position: absolute;
    top: 12px;
    left: 12px;
    right: 12px;
    z-index: 8;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    pointer-events: none;
}

.product-status-badge,
.product-descuento-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 900;
    letter-spacing: .04em;
    box-shadow: 0 10px 18px rgba(15,23,42,.12);
}

.product-status-badge-agotado {
    background: #fff1f2;
    color: #b91c1c;
}

.product-descuento-badge {
    margin-left: auto;
    background: #dcfce7;
    color: #166534;
}

.featured-filter-chip {
    border: 1px solid rgba(47,74,90,.1);
    background: rgba(255,255,255,.88);
    color: var(--store-text);
    border-radius: 999px;
    padding: 10px 16px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .04em;
    cursor: pointer;
    white-space: nowrap;
    transition: .2s ease;
}

.featured-filter-chip:hover,
.featured-filter-chip.is-active {
    background: var(--store-primary);
    color: #fff;
    border-color: var(--store-primary);
    box-shadow: 0 8px 18px rgba(32, 56, 100, .16);
}

.featured-products-empty {
    display: none;
    padding: 18px 20px;
    border-radius: 16px;
    border: 1px dashed #cfd8e3;
    background: rgba(255,255,255,.88);
    color: var(--store-muted);
    font-weight: 700;
    text-align: center;
}

@media (max-width: 991.98px) {
    .store-home-shell {
        padding: 0 14px 34px;
    }

    #mainCarousel .carousel-item {
        min-height: 340px;
    }

    .carousel-content {
        flex-direction: column;
        text-align: left;
        min-height: 340px;
        padding: 32px 36px 30px;
    }

    .carousel-text {
        max-width: 100%;
        padding-left: 0;
    }

    .carousel-image {
        width: 100%;
        min-height: 220px;
        transform: none;
    }

    .section-head {
        flex-direction: column;
        align-items: flex-start;
    }

    .categories-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .categories-carousel {
        padding: 0 50px;
    }
}

@media (max-width: 575.98px) {
    .main-content-offset {
        margin-top: 118px;
    }

    .store-home-shell {
        padding: 0 10px 28px;
    }

    #mainCarousel {
        border-radius: 22px;
    }

    #mainCarousel .carousel-control-prev,
    #mainCarousel .carousel-control-next {
        width: 36px !important;
        height: 36px !important;
        min-width: 36px;
        max-width: 36px;
    }

    .carousel-content {
        padding: 26px 18px 24px;
    }

    .carousel-image {
        min-height: 180px;
        border-radius: 18px;
    }

    .categories-row {
        grid-template-columns: 1fr;
    }

    .categories-carousel {
        padding: 0 36px;
    }

    .categories-section .container,
    .products-section .container {
        padding: 22px 16px;
        border-radius: 22px;
    }

    .category-card {
        min-height: 220px;
    }

    .category-card img {
        height: 122px;
        flex-basis: 122px;
    }
}
</style>

<div class="main-content-offset"></div>
<div class="store-home-shell">
<section class="home-hero">
    <div class="hero-carousel-panel">
        <div id="mainCarousel" class="carousel slide" data-ride="carousel" data-interval="8000">
    <div class="carousel-indicators">
        <?php 
        if(!empty($arrCategorias)){
            for($i = 0; $i < count($arrCategorias); $i++){ 
        ?>
            <li data-target="#mainCarousel" data-slide-to="<?= $i ?>" <?= $i == 0 ? 'class="active"' : '' ?>></li>
        <?php 
            }
        }
        ?>
    </div>
    <div class="carousel-inner">
        <?php 
        if(!empty($arrCategorias)){
            foreach($arrCategorias as $key => $categoria){ 
        ?>
            <div class="carousel-item <?= $key == 0 ? 'active' : '' ?>">
                <div class="carousel-content">
                    <div class="carousel-text">
                        <h2><?= strtoupper($categoria['nombre']) ?></h2>
                        <p><?= strtoupper($categoria['descripcion'] ? $categoria['descripcion'] : 'DESCUBRE NUESTROS PRODUCTOS EN ESTA CATEGORÍA') ?></p>
                        <a href="<?= appRoute('tienda_categoria', buildStoreParam((string)$categoria['nombre'], $categoria['idcategoria'])); ?>" class="btn btn-primary">VER PRODUCTOS</a>
                    </div>
                    <div class="carousel-image">
                        <?php $imagenCategoriaHero = (($categoria['portada'] ?? '') === 'favicon.ico') ? (base_url() . '/favicon.ico') : (base_url() . '/Assets/images/categorias/' . ($categoria['portada'] ?? 'favicon.ico')); ?>
                        <img src="<?= $imagenCategoriaHero ?>" alt="<?= strtoupper($categoria['nombre']) ?>" onerror="this.onerror=null;this.src='<?= base_url(); ?>/favicon.ico';">
                    </div>
                </div>
            </div>
        <?php 
            }
        }else{
        ?>
            <div class="carousel-item active">
                <div class="carousel-content">
                    <div class="carousel-text">
                        <h2><?= strtoupper(NOMBRE_EMPRESA) ?></h2>
                        <p>LOS MEJORES PRODUCTOS DE TECNOLOGÍA PARA TI</p>
                        <a href="<?= appRoute('tienda'); ?>" class="btn btn-primary">VER PRODUCTOS</a>
                    </div>
                    <div class="carousel-image">
                        <img src="<?= base_url(); ?>/favicon.ico" alt="DEFAULT">
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
    <a class="carousel-control-prev" href="#mainCarousel" role="button" data-slide="prev" aria-label="Anterior">
        <i class="fas fa-chevron-left" aria-hidden="true"></i>
    </a>
    <a class="carousel-control-next" href="#mainCarousel" role="button" data-slide="next" aria-label="Siguiente">
        <i class="fas fa-chevron-right" aria-hidden="true"></i>
    </a>
    <div class="carousel-progress" aria-hidden="true">
        <div id="mainCarouselProgress" class="carousel-progress-bar"></div>
    </div>
        </div>
    </div>
</section>

<!-- Categorías Destacadas -->
<section class="categories-section py-5">
    <div class="container">
        <div class="section-head">
            <div class="section-head-copy">
                <h2 class="section-title">CATEGORÍAS DESTACADAS</h2>
            </div>
        </div>
        <div class="categories-carousel">
            <a class="carousel-control-prev category-nav" href="#categoriesCarousel" role="button" data-slide="prev">
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
            </a>
            <div id="categoriesCarousel" class="carousel slide" data-ride="false" data-interval="false" data-wrap="false" data-keyboard="false" data-touch="false">
                <div class="carousel-inner">
                    <?php 
                    if(!empty($arrCategorias)){
                        $total = count($arrCategorias);
                        $slides = ceil($total / 4);
                        for($slide = 0; $slide < $slides; $slide++){ 
                    ?>
                    <div class="carousel-item <?= $slide == 0 ? 'active' : '' ?>">
                        <div class="categories-row">
                            <?php 
                            for($i = 0; $i < 4; $i++){ 
                                $index = ($slide * 4) + $i;
                                if($index < $total){ 
                                    $categoria = $arrCategorias[$index];
                            ?>
                            <div class="category-card">
                                <?php $imagenCategoriaCard = (($categoria['portada'] ?? '') === 'favicon.ico') ? (base_url() . '/favicon.ico') : (base_url() . '/Assets/images/categorias/' . ($categoria['portada'] ?? 'favicon.ico')); ?>
                                <img src="<?= $imagenCategoriaCard ?>" alt="<?= strtoupper($categoria['nombre']) ?>" onerror="this.onerror=null;this.src='<?= base_url(); ?>/favicon.ico';">
                                <div class="category-content">
                                    <h3><?= strtoupper($categoria['nombre']) ?></h3>
                                    <a href="<?= appRoute('tienda_categoria', buildStoreParam((string)$categoria['nombre'], $categoria['idcategoria'])); ?>" class="btn btn-outline-light">VER PRODUCTOS</a>
                                </div>
                            </div>
                            <?php 
                                }
                            } 
                            ?>
                        </div>
                    </div>
                    <?php }} ?>
                </div>
            </div>
            <a class="carousel-control-next category-nav" href="#categoriesCarousel" role="button" data-slide="next">
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</section>

<!-- Productos Destacados -->
<section class="products-section py-5 bg-light">
    <div class="container">
        <div class="section-head">
            <div class="section-head-copy">
                <h2 class="section-title">PRODUCTOS DESTACADOS</h2>
            </div>
        </div>
        <?php
        $categoriasFiltroDestacados = [];
        if (!empty($data['productos'])) {
            foreach ($data['productos'] as $productoFiltro) {
                $categoriaNombre = trim((string)($productoFiltro['categoria'] ?? ''));
                if ($categoriaNombre !== '') {
                    $claveCategoria = mb_strtolower($categoriaNombre, 'UTF-8');
                    $claveCategoria = strtr($claveCategoria, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
                    $categoriasFiltroDestacados[$claveCategoria] = $categoriaNombre;
                }
            }
        }
        ?>
        <div class="featured-filters-wrap">
            <span class="featured-filters-label"></span>
            <div class="featured-filters-list" id="featuredFiltersList">
                <button type="button" class="featured-filter-chip is-active" data-filter-type="all" data-filter-value="all">TODOS</button>
                <button type="button" class="featured-filter-chip" data-filter-type="estado" data-filter-value="descuento">PRODUCTOS EN DESCUENTO</button>
                <button type="button" class="featured-filter-chip" data-filter-type="estado" data-filter-value="agotado">PRODUCTOS AGOTADOS</button>
                <?php foreach ($categoriasFiltroDestacados as $categoriaClave => $categoriaNombre): ?>
                    <button type="button" class="featured-filter-chip" data-filter-type="categoria" data-filter-value="<?= htmlspecialchars($categoriaClave, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars(mb_strtoupper($categoriaNombre, 'UTF-8')); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="featured-products-empty" id="featuredProductsEmpty">No hay productos para ese filtro.</div>
        <div class="row" id="featuredProductsGrid">
            <?php
            if(!empty($data['productos'])){
                foreach($data['productos'] as $producto){ 
                    $claseAgotado = $producto['stock'] == 0 ? 'agotado' : '';
                    $agotado = ((int)($producto['stock'] ?? 0) <= 0);
                    $descuentoPct = (float)($producto['descuento_porcentaje'] ?? 0);
                    $claseDescuento = $descuentoPct > 0 ? 'con-descuento' : '';
                    $categoriaFiltro = trim((string)($producto['categoria'] ?? ''));
                    $categoriaFiltro = mb_strtolower($categoriaFiltro, 'UTF-8');
                    $categoriaFiltro = strtr($categoriaFiltro, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
                    $precioOriginal = (float)($producto['precio_original'] ?? $producto['precio'] ?? 0);
                    $precioFinal = (float)($producto['precio_final'] ?? $producto['precio'] ?? 0);
                    $urlImagenProducto = ((string)($producto['url_image'] ?? '') === 'favicon.ico')
                        ? (base_url() . '/favicon.ico')
                        : (base_url() . '/Assets/images/productos/' . ($producto['url_image'] ?? 'favicon.ico'));
            ?>
            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 mb-4 featured-product-item" data-categoria="<?= htmlspecialchars($categoriaFiltro, ENT_QUOTES, 'UTF-8'); ?>" data-descuento="<?= $descuentoPct > 0 ? '1' : '0'; ?>" data-agotado="<?= $agotado ? '1' : '0'; ?>">
                <div class="product-card <?= trim($claseAgotado . ' ' . $claseDescuento) ?>">
                    <div class="product-image position-relative">
                        <img src="<?= $urlImagenProducto ?>" alt="<?= strtoupper($producto['nombre']) ?>" onerror="this.onerror=null;this.src='<?= base_url(); ?>/favicon.ico';">
                        <div class="product-status-badges">
                            <?php if ($agotado): ?>
                            <span class="product-status-badge product-status-badge-agotado">AGOTADO</span>
                            <?php else: ?>
                            <span></span>
                            <?php endif; ?>
                            <?php if ($descuentoPct > 0): ?>
                            <span class="product-descuento-badge">Descuento -<?= number_format($descuentoPct, 0) ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-overlay">
                            <?php if (!$agotado): ?>
                            <a href="<?= appRoute('tienda_producto', buildStoreParam((string)$producto['nombre'], $producto['idproducto'])); ?>" class="btn btn-primary">VER DETALLES</a>
                            <?php else: ?>
                            <span class="btn btn-secondary disabled" style="pointer-events:none;">PRODUCTO AGOTADO</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="product-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?= strtoupper($producto['nombre']) ?></h5>
                            <button class="btn btn-link p-0 product-cart-btn btn-add-cart" type="button" style="text-decoration: none;"
                                    data-id="<?= $agotado ? '' : $producto['idproducto'] ?>"
                                    data-cantidad="1"
                                    <?= $agotado ? 'disabled aria-disabled="true"' : '' ?>
                                    aria-label="<?= $agotado ? 'Producto agotado' : 'Agregar al carrito' ?>">
                                <i class="fas <?= $agotado ? 'fa-ban' : 'fa-shopping-cart' ?>"></i>
                            </button>
                        </div>
                        <p class="product-category"><?= strtoupper($producto['categoria']) ?></p>
                        <?php if ($descuentoPct > 0 && $precioOriginal > $precioFinal): ?>
                        <div class="product-price mb-0" style="display:flex; flex-direction:column; align-items:flex-start; gap:4px;">
                            <span style="text-decoration:line-through; opacity:.65; font-size:14px;"><?= SMONEY.formatMoney($precioOriginal); ?></span>
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <span style="font-weight:700;"><?= SMONEY.formatMoney($precioFinal); ?></span>
                                <span class="badge badge-success">-<?= number_format($descuentoPct, 1); ?>%</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="product-price mb-0"><?= SMONEY.formatMoney($precioFinal); ?></p>
                        <?php endif; ?>
                        <?php if ($agotado): ?>
                        <p class="product-stock-note mb-0" style="color:#b91c1c;font-weight:700;">PRODUCTO NO DISPONIBLE. ESTARA DISPONIBLE LO MAS PRONTO POSIBLE.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php }} ?>
        </div>

    </div>
</section>

</div>

<script>
$(document).ready(function() {
    console.log('Documento listo');
    
    // Inicializar el carrusel principal
    var mainCarousel = $('#mainCarousel');
    console.log('Carrusel principal encontrado:', mainCarousel.length > 0);
    
    if(mainCarousel.length > 0) {
        mainCarousel.carousel({
            interval: 4000,
            wrap: true,
            pause: 'hover',
            keyboard: true
        });

        const progressBar = document.getElementById('mainCarouselProgress');
        let progressInterval;
        const tiempoSlide = 4000;

        function iniciarProgresoSlider() {
            if (!progressBar) return;
            clearInterval(progressInterval);
            let elapsed = 0;
            progressBar.style.width = '0%';

            progressInterval = setInterval(() => {
                elapsed += 100;
                const porcentaje = Math.min((elapsed / tiempoSlide) * 100, 100);
                progressBar.style.width = porcentaje + '%';

                if (elapsed >= tiempoSlide) {
                    clearInterval(progressInterval);
                }
            }, 100);
        }

        mainCarousel.on('slide.bs.carousel', function () {
            if (progressBar) {
                progressBar.style.width = '0%';
            }
            clearInterval(progressInterval);
        });

        mainCarousel.on('slid.bs.carousel', function () {
            iniciarProgresoSlider();
        });
        
        // Forzar el inicio del carrusel principal
        mainCarousel.carousel('cycle');
        iniciarProgresoSlider();
        
        // Pausar/reanudar barra cuando el usuario pasa el mouse
        mainCarousel.on('mouseenter', function () {
            clearInterval(progressInterval);
        });
        mainCarousel.on('mouseleave', function () {
            iniciarProgresoSlider();
        });
    }

    // Carrusel de categorías: solo manual con flechas
    var categoriesCarousel = $('#categoriesCarousel');
    if (categoriesCarousel.length > 0) {
        categoriesCarousel.carousel({
            interval: 4000,
            wrap: true,
            keyboard: true,
            pause: 'hover'
        });

        categoriesCarousel.find('.carousel-item').on('touchstart touchmove touchend', function(e) {
            e.stopPropagation();
        });
    }

    const featuredFiltersList = document.getElementById('featuredFiltersList');
    const featuredProductItems = Array.from(document.querySelectorAll('.featured-product-item'));
    const featuredProductsEmpty = document.getElementById('featuredProductsEmpty');

    function aplicarFiltroDestacados(tipo, valor) {
        let visibles = 0;

        featuredProductItems.forEach((item) => {
            let mostrar = true;

            if (tipo === 'estado' && valor === 'descuento') {
                mostrar = item.dataset.descuento === '1';
            } else if (tipo === 'estado' && valor === 'agotado') {
                mostrar = item.dataset.agotado === '1';
            } else if (tipo === 'categoria') {
                mostrar = item.dataset.categoria === valor;
            }

            item.style.display = mostrar ? '' : 'none';
            if (mostrar) {
                visibles += 1;
            }
        });

        if (featuredProductsEmpty) {
            featuredProductsEmpty.style.display = visibles === 0 ? 'block' : 'none';
        }
    }

    if (featuredFiltersList) {
        featuredFiltersList.addEventListener('click', function (event) {
            const chip = event.target.closest('.featured-filter-chip');
            if (!chip) {
                return;
            }

            featuredFiltersList.querySelectorAll('.featured-filter-chip').forEach((btn) => btn.classList.remove('is-active'));
            chip.classList.add('is-active');

            const tipo = chip.dataset.filterType || 'all';
            const valor = chip.dataset.filterValue || 'all';
            aplicarFiltroDestacados(tipo, valor);
        });
    }
});
</script>

<?php 
    footerTienda($data);
?> 