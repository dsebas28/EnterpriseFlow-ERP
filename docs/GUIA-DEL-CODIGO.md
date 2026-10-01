# Guía del código

Recorrido por el código de EnterpriseFlow para entender cómo está construido. Los fragmentos son copias literales del repositorio; los comentarios del código están en inglés, la explicación está en español.

## Contenido

1. [Cómo está organizado](#1-cómo-está-organizado)
2. [El recorrido de una petición](#2-el-recorrido-de-una-petición)
3. [Multiempresa: cómo se aíslan los datos](#3-multiempresa-cómo-se-aíslan-los-datos)
4. [Permisos](#4-permisos)
5. [Una venta de principio a fin](#5-una-venta-de-principio-a-fin)
6. [Inventario como libro de movimientos](#6-inventario-como-libro-de-movimientos)
7. [Dinero sin errores de redondeo](#7-dinero-sin-errores-de-redondeo)
8. [Eventos, colas y notificaciones](#8-eventos-colas-y-notificaciones)
9. [API REST](#9-api-rest)
10. [Frontend](#10-frontend)
11. [Tests](#11-tests)

## 1. Cómo está organizado

```
app/
├── Actions/          Casos de uso: una clase = una operación de negocio (ConfirmSale, RecordPayment...)
├── DTOs/             Datos de entrada tipados e inmutables (SaleData, PaymentData...)
├── Enums/            Estados y catálogos con sus reglas (SaleStatus sabe a qué estados puede pasar)
├── Events/           Hechos de negocio (SaleConfirmed, PaymentReceived...)
├── Http/
│   ├── Controllers/  Controladores delgados: reciben, delegan en una Action y responden
│   ├── Requests/     Validación y autorización de cada formulario
│   └── Resources/    Cómo se convierte cada modelo a JSON
├── Jobs/             Trabajo en segundo plano (PDF de facturas, exportaciones, webhooks)
├── Listeners/        Reaccionan a los eventos (enviar notificaciones)
├── Models/           Modelos Eloquent
├── Policies/         Quién puede hacer qué con cada modelo
├── Queries/          Listados con filtros, búsqueda y orden
├── Reports/          Definición de los 10 reportes
├── Services/         Lógica compartida (inventario, permisos, finanzas, notificaciones)
└── Support/          Piezas transversales: dinero, multiempresa, API, auditoría, salud
```

La regla principal: **la lógica de negocio vive en las Actions**, nunca en los controladores. Así la misma Action la usan la web, la API, los jobs, los webhooks y el seeder de la demo, y cada regla existe una sola vez.

## 2. El recorrido de una petición

Cuando un usuario confirma una venta desde la web:

```
Navegador ─POST /sales/orders/{sale}/confirm─▶ Middleware
   │  auth (sesión) ▸ verified ▸ tenant (empresa activa) ▸ SubstituteBindings (carga la venta)
   ▼
SaleWorkflowController::confirm
   │  Gate::authorize('confirm', $sale)  ← SalePolicy: ¿tiene el permiso sales.confirm?
   ▼
ConfirmSale::handle($sale, $user)       ← transacción, bloqueo de fila, stock, costo
   ▼
SaleConfirmed (evento, después del commit) ─▶ notificaciones en cola
   ▼
redirect back con mensaje de éxito
```

El middleware `tenant` va **antes** de cargar el modelo de la URL: si la venta es de otra empresa, ni siquiera se encuentra (404).

## 3. Multiempresa: cómo se aíslan los datos

Todos los modelos de negocio usan el trait `BelongsToCompany`, que registra un filtro global (`CompanyScope`):

```php
public function apply(Builder $builder, Model $model): void
{
    $context = app(TenantContext::class);

    if ($context->isBypassed()) {
        return;
    }

    $builder->where($model->qualifyColumn('company_id'), $context->idOrFail());
}
```

- `idOrFail()` lanza una excepción si no hay empresa activa. Es **"fail-closed"**: olvidarse de elegir empresa produce un error, nunca datos de otra empresa.
- El trait además rellena `company_id` al crear y prohíbe cambiarlo después.
- En la base de datos, las claves foráneas compuestas `(company_id, id)` impiden mezclar empresas aunque fallara el código (ver [BASE-DE-DATOS.md](BASE-DE-DATOS.md)).
- Un test recorre **todas las rutas** de la aplicación con registros de otra empresa y exige 404 en cada una (`tests/Feature/Security/RouteProtectionTest.php`).

## 4. Permisos

- Cada empresa tiene sus roles (7 plantillas de sistema más los que se creen). Un rol es una lista de permisos `módulo.acción`: `sales.confirm`, `invoices.view`...
- `PermissionResolver` calcula los permisos del usuario **en la empresa activa** con una sola consulta y los memoriza durante la petición.
- Cada permiso se registra como un *Gate* de Laravel, y las Policies los usan: `SalePolicy::confirm` comprueba `sales.confirm`.
- En el frontend los permisos solo ocultan botones; la seguridad real siempre la decide el backend.

## 5. Una venta de principio a fin

`app/Actions/Sales/ConfirmSale.php` es un buen ejemplo de cómo están escritas las Actions:

```php
public function handle(Sale $sale, User $user): Sale
{
    $confirmed = DB::transaction(function () use ($sale, $user): Sale {
        $sale = Sale::lockForUpdate()->findOrFail($sale->id);
        $sale->transitionTo(SaleStatus::Confirmed);

        $items = $sale->items()->with('product')->get();

        if ($items->isEmpty()) {
            throw new BusinessRuleViolation('Add at least one line before confirming the sale.');
        }

        if ($sale->customer->status !== PartyStatus::Active) {
            throw new BusinessRuleViolation("Customer {$sale->customer->name} is inactive.");
        }

        $this->inventory->recordMany($items->map(fn (SaleItem $item) => new StockMovementData(
            product: $item->product,
            warehouse: $sale->warehouse,
            quantity: -$item->quantity,
            type: StockMovementType::Sale,
            reference: $sale,
            notes: "Sale {$sale->number}",
        ))->values()->all());

        // Snapshot the cost at the moment of sale, for margin reporting.
        foreach ($items as $item) {
            $item->forceFill(['unit_cost' => $item->product->cost])->save();
        }

        $sale->forceFill(['confirmed_by' => $user->id, 'confirmed_at' => now()])->save();

        return $sale;
    });

    SaleConfirmed::dispatch($confirmed);

    return $confirmed;
}
```

Paso a paso:

1. **Transacción**: o se hace todo o no se hace nada.
2. **`lockForUpdate()`**: bloquea la fila de la venta. Si dos personas confirman a la vez, la segunda espera y luego falla porque la venta ya no está en borrador.
3. **`transitionTo()`**: el enum `SaleStatus` define qué cambios de estado son válidos; uno inválido lanza `InvalidStateTransition`.
4. **Reglas de negocio**: si fallan, `BusinessRuleViolation` se muestra al usuario como un error 422 con un mensaje claro.
5. **Inventario**: descuenta el stock de todas las líneas a la vez (si falta stock de alguna, no se descuenta ninguna).
6. **Costo histórico**: guarda el costo de cada producto en la línea, para que el margen de esa venta no cambie si mañana cambia el costo.
7. **Evento**: `SaleConfirmed` se dispara **después** del commit (`ShouldDispatchAfterCommit`): nunca se notifica algo que terminó en rollback.

## 6. Inventario como libro de movimientos

El stock no es un número que se edita: es la suma de movimientos. `InventoryService::recordMany` aplica varios movimientos en una transacción y **siempre en el mismo orden**, para que dos operaciones simultáneas no se bloqueen mutuamente (deadlock):

```php
return DB::transaction(function () use ($movements): array {
    $ordered = $movements;
    uasort($ordered, fn (StockMovementData $a, StockMovementData $b) => strcmp($a->lockKey(), $b->lockKey()));

    $recorded = [];
    foreach ($ordered as $index => $movement) {
        $recorded[$index] = $this->apply($movement);
    }
    ksort($recorded);

    return array_values($recorded);
});
```

Cada movimiento guarda `balance_after` (el saldo resultante) y actualiza la tabla `stock_levels`, que es solo una proyección: el comando `php artisan inventory:rebuild` la reconstruye desde el libro. Un trigger en PostgreSQL impide modificar o borrar movimientos.

## 7. Dinero sin errores de redondeo

Los importes se guardan como **enteros en la unidad mínima** de la moneda (centavos) y se calculan con el objeto `Money`. Así se calcula una línea de venta (`app/Support/Documents/LineCalculator.php`):

```php
$gross = (new Money($unitPrice, $currency))->multiply($quantity);
$discount = $gross->percentage($discountRate);
$subtotal = $gross->subtract($discount);
$tax = $subtotal->percentage($taxRate);
```

Los porcentajes van en puntos básicos (1900 = 19 %) y se redondea por línea, "half-up", como en una factura real. El frontend nunca recalcula importes: recibe `{amount, decimal, currency}` del servidor y solo les da formato.

## 8. Eventos, colas y notificaciones

- Los eventos (`SaleConfirmed`, `PaymentReceived`, `StockFellBelowMinimum`...) los escucha `SendBusinessNotifications`, que decide **a quién** avisar según el permiso de cada categoría (por ejemplo, stock bajo a quien puede comprar).
- Las notificaciones, los PDF de facturas y las exportaciones van a **colas en Redis** con prioridades: un export pesado no retrasa el registro de un pago.
- Los jobs guardan solo ids y la empresa; el middleware `RestoreTenantContext` restaura la empresa en el worker antes de ejecutar.
- Los **webhooks** de pagos se verifican con firma HMAC, se guardan una sola vez (índice único por proveedor e id del evento) y se procesan en cola con reintentos.

## 9. API REST

- Rutas en `routes/api.php` bajo `/api/v1`, controladores en `app/Http/Controllers/Api/V1`.
- Reutilizan las mismas Actions, Requests y Policies que la web.
- Todas las respuestas tienen el mismo formato (`App\Support\Api\ApiResponse`): `{ success, data, message, meta }`.
- Los errores se convierten en un solo lugar (`ApiExceptionRenderer`) a 401, 403, 404, 422, 429 o 500.
- La documentación OpenAPI se genera desde el código (`docs/openapi.json` y `/docs/api`).

## 10. Frontend

- **Vue 3 + Inertia + TypeScript**: cada página es un componente en `resources/js/pages`, y el controlador le pasa los datos como props (sin escribir una API aparte para la web).
- Componentes de interfaz en `resources/js/components/ui` (shadcn-vue) y propios en `resources/js/components`.
- Colores de marca en `resources/css/app.css` (índigo y ámbar del logo) y `tailwind.config.js`.
- El gráfico de ventas (`components/charts/DailySalesChart.vue`) está hecho a mano en SVG, con tooltip, foco por teclado y una tabla oculta para lectores de pantalla.

## 11. Tests

Más de 530 tests con **Pest**, que se ejecutan en CI sobre SQLite y **PostgreSQL**:

| Carpeta | Qué prueba |
|---|---|
| `tests/Unit` | Dinero, cálculo de líneas, firmas de webhooks y **reglas de arquitectura** (por ejemplo: los controladores no hacen SQL, `env()` solo en `config/`) |
| `tests/Feature/<Módulo>` | Cada módulo de punta a punta: ventas, compras, inventario, facturas, reportes con cifras exactas... |
| `tests/Feature/Security` | Recorren todas las rutas: exigen login, 403 sin permiso y 404 con datos de otra empresa |
| `tests/Feature/PageRenderTest.php` | Cada página se renderiza con datos reales |

Comandos útiles:

```bash
composer test            # todos los tests en paralelo
composer test:arch       # solo las reglas de arquitectura
composer ci              # estilo (Pint) + análisis estático (Larastan) + tests
```
