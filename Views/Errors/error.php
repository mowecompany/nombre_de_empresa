<?php
headerTienda($data);
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <div class="error-container">
                <h1 class="display-1 text-muted">404</h1>
                <h2 class="mb-4">Página no encontrada</h2>
                <p class="lead text-muted mb-4">Lo sentimos, la página que estás buscando no existe.</p>
                <a href="<?= base_url(); ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-home me-2"></i>Volver al inicio
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.error-container {
    padding: 3rem;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}
.error-container h1 {
    font-size: 8rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 1rem;
}
.error-container h2 {
    font-size: 2rem;
    font-weight: 600;
    color: #333;
}
.error-container .lead {
    font-size: 1.25rem;
    color: #666;
}
</style>

<?php 
footerTienda($data);
?> 