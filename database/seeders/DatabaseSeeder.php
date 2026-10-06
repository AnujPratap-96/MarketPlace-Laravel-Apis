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
        $admin = User::firstOrCreate(
            ['email' => 'admin@marketplace.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password123'),
                'role' => UserRole::ADMIN,
            ]
        );

        // 2. Create Vendors
        $vendorUser1 = User::firstOrCreate(
            ['email' => 'tech@vendor.com'],
            [
                'name' => 'Alex Tech Merchant',
                'password' => Hash::make('password123'),
                'role' => UserRole::VENDOR,
            ]
        );

        $vendor1 = Vendor::firstOrCreate(
            ['user_id' => $vendorUser1->id],
            [
                'store_name' => 'TechZone Electronics',
                'slug' => 'techzone-electronics',
                'commission_rate' => 10.00,
                'balance_in_cents' => 0,
                'is_verified' => true,
            ]
        );

        $vendorUser2 = User::firstOrCreate(
            ['email' => 'fashion@vendor.com'],
            [
                'name' => 'Sophia Fashion Merchant',
                'password' => Hash::make('password123'),
                'role' => UserRole::VENDOR,
            ]
        );

        $vendor2 = Vendor::firstOrCreate(
            ['user_id' => $vendorUser2->id],
            [
                'store_name' => 'Aura Luxury Apparel',
                'slug' => 'aura-luxury-apparel',
                'commission_rate' => 12.50,
                'balance_in_cents' => 0,
                'is_verified' => true,
            ]
        );

        // 3. Create Retail Customer
        $customer = User::firstOrCreate(
            ['email' => 'customer@gmail.com'],
            [
                'name' => 'Rahul Sharma',
                'password' => Hash::make('password123'),
                'role' => UserRole::CUSTOMER,
            ]
        );

        // 4. Create Categories
        $electronics = Category::firstOrCreate(
            ['slug' => 'electronics'],
            ['name' => 'Electronics']
        );

        $laptops = Category::firstOrCreate(
            ['slug' => 'laptops-computers'],
            [
                'parent_id' => $electronics->id,
                'name' => 'Laptops & Computers',
            ]
        );

        $fashion = Category::firstOrCreate(
            ['slug' => 'apparel-fashion'],
            ['name' => 'Apparel & Fashion']
        );

        // 5. Create Products
        Product::firstOrCreate(
            ['slug' => 'probook-ultra-16-m3'],
            [
                'vendor_id' => $vendor1->id,
                'category_id' => $laptops->id,
                'name' => 'ProBook Ultra 16 M3',
                'description' => 'Flagship 16-inch developer laptop with 32GB RAM and 1TB SSD.',
                'price_in_cents' => 189900,
                'stock' => 10,
                'is_active' => true,
            ]
        );

        Product::firstOrCreate(
            ['slug' => 'wireless-anc-headphones-x1'],
            [
                'vendor_id' => $vendor1->id,
                'category_id' => $electronics->id,
                'name' => 'Wireless ANC Headphones X1',
                'description' => 'Active noise cancelling studio monitors with 40h battery life.',
                'price_in_cents' => 24900,
                'stock' => 25,
                'is_active' => true,
            ]
        );

        Product::firstOrCreate(
            ['slug' => 'italian-merino-wool-coat'],
            [
                'vendor_id' => $vendor2->id,
                'category_id' => $fashion->id,
                'name' => 'Italian Merino Wool Coat',
                'description' => 'Handcrafted tailor-fit luxury winter overcoat in charcoal black.',
                'price_in_cents' => 45000,
                'stock' => 5,
                'is_active' => true,
            ]
        );
    }
}
