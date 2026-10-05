<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Administrator
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@marketplace.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::ADMIN,
        ]);

        // 2. Create Vendors
        $vendorUser1 = User::create([
            'name' => 'Alex Tech Merchant',
            'email' => 'tech@vendor.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::VENDOR,
        ]);

        $vendor1 = Vendor::create([
            'user_id' => $vendorUser1->id,
            'store_name' => 'TechZone Electronics',
            'slug' => 'techzone-electronics',
            'commission_rate' => 10.00,
            'balance_in_cents' => 0,
            'is_verified' => true,
        ]);

        $vendorUser2 = User::create([
            'name' => 'Sophia Fashion Merchant',
            'email' => 'fashion@vendor.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::VENDOR,
        ]);

        $vendor2 = Vendor::create([
            'user_id' => $vendorUser2->id,
            'store_name' => 'Aura Luxury Apparel',
            'slug' => 'aura-luxury-apparel',
            'commission_rate' => 12.50,
            'balance_in_cents' => 0,
            'is_verified' => true,
        ]);

        // 3. Create Retail Customer
        $customer = User::create([
            'name' => 'Rahul Sharma',
            'email' => 'customer@gmail.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::CUSTOMER,
        ]);

        // 4. Create Categories
        $electronics = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        $laptops = Category::create([
            'parent_id' => $electronics->id,
            'name' => 'Laptops & Computers',
            'slug' => 'laptops-computers',
        ]);

        $fashion = Category::create([
            'name' => 'Apparel & Fashion',
            'slug' => 'apparel-fashion',
        ]);

        // 5. Create Products
        Product::create([
            'vendor_id' => $vendor1->id,
            'category_id' => $laptops->id,
            'name' => 'ProBook Ultra 16 M3',
            'slug' => 'probook-ultra-16-m3',
            'description' => 'Flagship 16-inch developer laptop with 32GB RAM and 1TB SSD.',
            'price_in_cents' => 189900, // $1,899.00
            'stock' => 10,
            'is_active' => true,
        ]);

        Product::create([
            'vendor_id' => $vendor1->id,
            'category_id' => $electronics->id,
            'name' => 'Wireless ANC Headphones X1',
            'slug' => 'wireless-anc-headphones-x1',
            'description' => 'Active noise cancelling studio monitors with 40h battery life.',
            'price_in_cents' => 24900, // $249.00
            'stock' => 25,
            'is_active' => true,
        ]);

        Product::create([
            'vendor_id' => $vendor2->id,
            'category_id' => $fashion->id,
            'name' => 'Italian Merino Wool Coat',
            'slug' => 'italian-merino-wool-coat',
            'description' => 'Handcrafted tailor-fit luxury winter overcoat in charcoal black.',
            'price_in_cents' => 45000, // $450.00
            'stock' => 5,
            'is_active' => true,
        ]);
    }
}
