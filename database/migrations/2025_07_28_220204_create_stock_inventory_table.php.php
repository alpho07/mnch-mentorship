<?php

// Migration: create_inventory_items_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 50)->unique();
            $table->string('color', 7)->nullable()->comment('Hex color code for UI display');
            $table->string('icon', 100)->nullable()->comment('Icon class or name');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0)->comment('Order for display sorting');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['is_active', 'sort_order']);
            $table->index(['parent_id', 'is_active']);
            $table->index(['code', 'is_active']);

            // Foreign key constraint for parent category
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('inventory_categories')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_categories');
    }
};


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique()->comment('Unique supplier code/ID');
            $table->text('description')->nullable();
            $table->string('supplier_type')->default('vendor')->comment('vendor, manufacturer, distributor, contractor');

            // Primary Contact Information
            $table->string('contact_person')->nullable();
            $table->string('contact_title')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Address Information
            $table->text('address_line_1')->nullable();
            $table->text('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state_province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();

            // Business Information
            $table->string('tax_id')->nullable()->comment('Tax identification number');
            $table->string('registration_number')->nullable()->comment('Business registration number');
            $table->string('vat_number')->nullable()->comment('VAT registration number');
            $table->date('established_date')->nullable();

            // Financial Information
            $table->string('currency', 3)->default('USD');
            $table->string('payment_terms')->nullable()->comment('e.g., Net 30, COD, etc.');
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_routing')->nullable();

            // Performance & Rating
            $table->decimal('rating', 3, 2)->nullable()->comment('Supplier rating out of 5.00');
            $table->text('rating_notes')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_certified')->default(false);
            $table->json('certifications')->nullable()->comment('List of certifications');

            // Contract & Legal
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->boolean('has_nda')->default(false);
            $table->boolean('has_insurance')->default(false);
            $table->decimal('insurance_amount', 15, 2)->nullable();

            // Operational Information
            $table->json('lead_times')->nullable()->comment('Standard lead times by product category');
            $table->json('shipping_methods')->nullable();
            $table->decimal('minimum_order_amount', 12, 2)->nullable();
            $table->json('service_areas')->nullable()->comment('Geographic areas served');

            // Status & Notes
            $table->string('status')->default('active')->comment('active, inactive, suspended, blacklisted');
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable()->comment('Internal notes not visible to supplier');

            // Tracking
            $table->timestamp('last_order_date')->nullable();
            $table->decimal('total_orders_value', 15, 2)->default(0);
            $table->integer('total_orders_count')->default(0);
            $table->timestamp('last_contact_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'is_preferred']);
            $table->index(['supplier_type', 'status']);
            $table->index(['country', 'state_province', 'city']);
            $table->index(['is_preferred', 'rating']);
            $table->index(['contract_end_date', 'status']);
            $table->index(['last_order_date', 'status']);
            $table->index('code');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->foreignId('category_id')->constrained('inventory_categories');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $table->string('unit_of_measure');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->integer('minimum_stock_level')->default(0);
            $table->integer('maximum_stock_level')->default(0);
            $table->integer('reorder_point')->default(0);
            $table->integer('reorder_quantity')->default(0);
            $table->boolean('is_trackable')->default(false);
            $table->boolean('is_serialized')->default(false);
            $table->boolean('requires_batch_tracking')->default(false);
            $table->integer('shelf_life_days')->nullable();
            $table->decimal('weight', 8, 3)->nullable();
            $table->json('dimensions')->nullable();
            $table->json('storage_requirements')->nullable();
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_trackable']);
            $table->index(['category_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_items');
    }
};

// Migration: create_stock_levels_table.php
return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('location_id');
            $table->enum('location_type', ['main_store', 'facility'])->default('facility');
            $table->integer('current_stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->integer('projected_stock')->default(0);
            $table->foreignId('last_updated_by')->nullable()->constrained('users');
            $table->timestamp('last_stock_take_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['inventory_item_id', 'location_id', 'location_type']);
            $table->index(['location_id', 'location_type']);
            $table->index(['current_stock', 'reserved_stock']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_levels');
    }
};

// Migration: create_inventory_transactions_table.php
return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('serial_number_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->onDelete('set null');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->enum('location_type', ['main_store', 'facility'])->nullable();
            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->enum('from_location_type', ['main_store', 'facility'])->nullable();
            $table->unsignedBigInteger('to_location_id')->nullable();
            $table->enum('to_location_type', ['main_store', 'facility'])->nullable();
            $table->enum('type', ['in', 'out', 'transfer', 'adjustment', 'request', 'issue', 'return', 'damage', 'loss', 'disposal']);
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamp('transaction_date');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index(['transaction_date', 'type']);
            $table->index(['location_id', 'location_type']);
            $table->index(['from_location_id', 'to_location_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_transactions');
    }
};

// Migration: create_stock_requests_table.php
return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('requesting_facility_id')->constrained('facilities');
            $table->foreignId('supplying_facility_id')->nullable()->constrained('facilities');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent', 'emergency'])->default('normal');
            $table->enum('status', ['draft', 'submitted', 'approved', 'partially_fulfilled', 'fulfilled', 'rejected', 'cancelled'])->default('draft');
            $table->timestamp('request_date');
            $table->timestamp('required_by_date')->nullable();
            $table->timestamp('approved_date')->nullable();
            $table->timestamp('fulfilled_date')->nullable();
            $table->decimal('total_estimated_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('justification')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['requesting_facility_id', 'status']);
            $table->index(['request_date', 'required_by_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_requests');
    }
};

// Migration: create_stock_request_items_table.php
return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('inventory_item_id')->constrained();
            $table->integer('quantity_requested');
            $table->integer('quantity_approved')->default(0);
            $table->integer('quantity_fulfilled')->default(0);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->enum('urgency_level', ['low', 'medium', 'high'])->default('medium');
            $table->text('justification')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_request_id', 'inventory_item_id']);
            $table->index(['quantity_requested', 'quantity_approved']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_request_items');
    }
};

// Migration: create_stock_transfers_table.php
return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('from_facility_id')->nullable()->constrained('facilities');
            $table->foreignId('to_facility_id')->constrained('facilities');
            $table->foreignId('initiated_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->enum('status', ['draft', 'pending', 'approved', 'in_transit', 'delivered', 'received', 'cancelled'])->default('draft');
            $table->timestamp('transfer_date')->nullable();
            $table->timestamp('expected_arrival_date')->nullable();
            $table->timestamp('actual_arrival_date')->nullable();
            $table->integer('total_items')->default(0);
            $table->decimal('total_value', 12, 2)->default(0);
            $table->enum('transport_method', ['road', 'air', 'rail', 'courier', 'hand_delivery', 'other'])->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'transfer_date']);
            $table->index(['from_facility_id', 'to_facility_id']);
            $table->index(['expected_arrival_date', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_transfers');
    }
};

// Migration: create_stock_transfer_items_table.php
return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->onDelete('cascade');
            $table->foreignId('inventory_item_id')->constrained();
            $table->integer('quantity');
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->foreignId('batch_id')->nullable()->constrained('item_batches');
            $table->json('serial_numbers')->nullable();
            $table->text('condition_notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_transfer_id', 'inventory_item_id']);
            $table->index(['quantity', 'quantity_received']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_transfer_items');
    }
};

// Migration: create_facility_inventory_items_table.php
return new class extends Migration
{
    public function up()
    {
        Schema::create('facility_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('inventory_item_id')->constrained()->onDelete('cascade');
            $table->integer('minimum_level')->default(0);
            $table->integer('maximum_level')->default(0);
            $table->integer('current_stock')->default(0);
            $table->timestamps();

            $table->unique(['facility_id', 'inventory_item_id']);
            $table->index(['current_stock', 'minimum_level']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('facility_inventory_items');
    }
};
