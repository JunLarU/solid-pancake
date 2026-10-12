<?php
// estudiantes/estudiantes.php
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CAFI - Menú del Estudiante</title>

    <link rel="stylesheet" href="/estudiantes/styles.css">
</head>

<body>

<header class="topbar">

    <div>
        <h1>🎓 CAFI</h1>
        <p>Panel del Estudiante</p>
    </div>

    <div class="header-actions">

        <span id="welcome">
            👋 Bienvenido
        </span>

        <button
            id="refreshAll"
            class="btn btn-secondary">
            ↻ Actualizar
        </button>

    </div>

</header>


<main class="container">

    <!-- ===================================================== -->
    <!-- PESTAÑAS -->
    <!-- ===================================================== -->

    <nav class="tabs">

        <button
            class="tab active"
            data-tab="menu">

            📅 Menú semanal

        </button>

        <button
            class="tab"
            data-tab="avisos">

            📢 Avisos

        </button>

        <button
            class="tab"
            data-tab="especiales">

            ⭐ Especiales

        </button>

    </nav>


    <!-- ===================================================== -->
    <!-- MENÚ -->
    <!-- ===================================================== -->

    <section
        id="panel-menu"
        class="panel active">

        <div class="toolbar">

            <div>

                <h2 id="weekTitle">
                    Semana actual
                </h2>

                <p
                    id="weekRange"
                    class="muted">
                </p>

            </div>


            <div class="toolbar-controls">

                <label>

                    Horario

                    <select id="scheduleFilter">

                        <option value="Todos">
                            Todos
                        </option>

                        <option value="Desayuno">
                            Desayuno
                        </option>

                        <option value="Comida">
                            Comida
                        </option>

                    </select>

                </label>


                <button
                    id="prevWeek"
                    class="btn btn-secondary">

                    ◀ Semana anterior

                </button>


                <button
                    id="todayWeek"
                    class="btn btn-light">

                    Semana actual

                </button>


                <button
                    id="nextWeek"
                    class="btn btn-primary">

                    Semana siguiente ▶

                </button>

            </div>

        </div>

        <!-- Filtros del lado del cliente -->
        <div class="client-filters">
            <div class="search-box">
                <span>🔎</span>
                <input id="menuSearch"
                       type="search"
                       placeholder="Buscar platillo, categoría o ingrediente...">
            </div>

            <div class="day-filter" id="dayFilter">
                <button class="day-chip active" data-day="Todos">Todos</button>
                <button class="day-chip" data-day="Lunes">Lun</button>
                <button class="day-chip" data-day="Martes">Mar</button>
                <button class="day-chip" data-day="Miércoles">Mié</button>
                <button class="day-chip" data-day="Jueves">Jue</button>
                <button class="day-chip" data-day="Viernes">Vie</button>
            </div>

            <label class="filter-control">
                Categoría
                <select id="categoryFilter">
                    <option value="Todos">Todas</option>
                </select>
            </label>

            <label class="filter-control">
                Ordenar
                <select id="sortFilter">
                    <option value="default">Predeterminado</option>
                    <option value="price-asc">Precio: menor a mayor</option>
                    <option value="price-desc">Precio: mayor a menor</option>
                    <option value="calories-asc">Calorías: menor a mayor</option>
                    <option value="calories-desc">Calorías: mayor a menor</option>
                    <option value="name-asc">Nombre A-Z</option>
                </select>
            </label>

            <button id="clearFilters" class="btn btn-light clear-filter-btn">
                Limpiar filtros
            </button>
        </div>


        <div
            id="menuStatus"
            class="status loading">

            Cargando menú...

        </div>


        <div
            id="menuContainer"
            class="day-list">
        </div>

    </section>


    <!-- ===================================================== -->
    <!-- AVISOS -->
    <!-- ===================================================== -->

    <section
        id="panel-avisos"
        class="panel">

        <div class="toolbar">

            <div>

                <h2>
                    📢 Avisos
                </h2>

                <p class="muted">
                    Información vigente de la cafetería y Cafecito.
                </p>

            </div>


            <label>

                Filtrar por

                <select id="noticeFilter">

                    <option value="Todos">
                        Todos
                    </option>

                    <option value="Cafetería">
                        Cafetería
                    </option>

                    <option value="Cafecito">
                        Cafecito
                    </option>

                    <option value="Importantes">
                        Importantes
                    </option>

                </select>

            </label>

        </div>


        <div
            id="noticeStatus"
            class="status loading">

            Cargando avisos...

        </div>


        <div
            id="noticeContainer"
            class="notice-list">
        </div>

    </section>


    <!-- ===================================================== -->
    <!-- ESPECIALES -->
    <!-- ===================================================== -->

    <section
        id="panel-especiales"
        class="panel">

        <div class="section-heading">

            <div>

                <span class="eyebrow">
                    PROMOCIONES
                </span>

                <h2>
                    ⭐ Ofertas y productos especiales
                </h2>

                <p class="muted">
                    Promociones vigentes disponibles en CAFI.
                </p>

            </div>

        </div>


        <div
            id="specialStatus"
            class="status loading">

            Cargando promociones...

        </div>


        <div
            id="specialContainer"
            class="special-grid">
        </div>

    </section>

</main>


<!-- ========================================================= -->
<!-- MODAL PRODUCTO -->
<!-- ========================================================= -->

<div
    id="productModal"
    class="modal"
    aria-hidden="true">

    <div
        class="modal-backdrop"
        data-close-modal>
    </div>


    <article
        class="modal-card">

        <button
            class="modal-close"
            data-close-modal>

            ×

        </button>


        <div id="modalContent">
        </div>

    </article>

</div>


<!-- ========================================================= -->
<!-- TEMPLATE VACÍO -->
<!-- ========================================================= -->

<template id="emptyTemplate">

    <div class="empty-state">

        <div class="empty-icon">
            🍽️
        </div>

        <h3>
            Sin información disponible
        </h3>

        <p>
            No hay contenido para mostrar.
        </p>

    </div>

</template>


<script src="/estudiantes/app.js"></script>

</body>
</html>