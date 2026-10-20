<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Landing Page — Fresh Green & White Modern Web UI
 */

declare(strict_types=1);

$pageTitle = 'Fresh Chef-Crafted Meals Delivered Fast';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- =========================================================================
     HERO SECTION (Two-Column Layout with Real Gourmet Visuals)
     ========================================================================= -->
<section style="margin: 1.5rem 0 4.5rem;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 3.5rem; align-items: center;">
        
        <!-- Left Column: Copy & Actions -->
        <div>
            <h1 style="font-size: 3.1rem; font-weight: 700; color: var(--text-main); line-height: 1.25; margin-bottom: 1.25rem; letter-spacing: normal;">
                Fresh Gourmet Meals, <br>
                <span class="brand-highlight">Straight to Your Door.</span>
            </h1>

            <p style="font-size: 1.15rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 2.25rem;">
                Zero dining-room markups, 100% culinary dedication. UrbanEats prepares restaurant-grade dishes on demand and delivers them steaming hot across Colombo.
            </p>

            <?php if (!is_logged_in()): ?>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2.5rem;">
                    <a href="<?= base_url('auth/login.php') ?>" class="btn btn-primary" style="padding: 0.9rem 2rem; font-size: 1rem;">
                        Sign In to Order &rarr;
                    </a>
                    <a href="<?= base_url('auth/register.php') ?>" class="btn btn-outline" style="padding: 0.9rem 2rem; font-size: 1rem;">
                        Create Free Account
                    </a>
                </div>
            <?php else: ?>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2.5rem;">
                    <?php if (current_user_role() === 'admin'): ?>
                        <a href="<?= base_url('admin/index.php') ?>" class="btn btn-primary" style="padding: 0.9rem 2rem; font-size: 1rem;">
                            Open Admin Dashboard &rarr;
                        </a>
                        <a href="<?= base_url('admin/users.php') ?>" class="btn btn-outline" style="padding: 0.9rem 1.75rem; font-size: 1rem;">
                            Manage Users
                        </a>
                    <?php else: ?>
                        <a href="<?= base_url('auth/profile.php') ?>" class="btn btn-primary" style="padding: 0.9rem 2rem; font-size: 1rem;">
                            View My Profile &rarr;
                        </a>
                        <a href="<?= base_url('auth/change-password.php') ?>" class="btn btn-outline" style="padding: 0.9rem 1.75rem; font-size: 1rem;">
                            Security Settings
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Trust Badges -->
            <div style="display: flex; gap: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.75rem; flex-wrap: wrap;">
                <div>
                    <strong style="display: block; font-size: 1.2rem; color: var(--text-main);">⭐ 4.9 / 5.0</strong>
                    <span style="font-size: 0.825rem; color: var(--text-dim);">1,200+ Verified Orders</span>
                </div>
                <div>
                    <strong style="display: block; font-size: 1.2rem; color: var(--primary);">⚡ 25–35 Min</strong>
                    <span style="font-size: 0.825rem; color: var(--text-dim);">Average Prep & Delivery</span>
                </div>
                <div>
                    <strong style="display: block; font-size: 1.2rem; color: var(--text-main);">🌿 100% Fresh</strong>
                    <span style="font-size: 0.825rem; color: var(--text-dim);">Sourced Daily</span>
                </div>
            </div>
        </div>

        <!-- Right Column: Picture Visual with Floating Badges -->
        <div style="position: relative;">
            <div style="position: relative; border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
                <img src="<?= base_url('images/hero_dish.jpg') ?>"
                     alt="Artisanal Teriyaki Chicken Bowl with Fresh Avocado"
                     style="width: 100%; height: 420px; object-fit: cover;">
                <div style="position: absolute; inset: 0; background: linear-gradient(180deg, transparent 65%, rgba(15, 23, 42, 0.4) 100%);"></div>
            </div>

            <!-- Floating Badge Top Left -->
            <div style="position: absolute; top: 18px; left: 18px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 0.65rem 1rem; border-radius: var(--radius-md); box-shadow: var(--shadow-md); display: flex; align-items: center; gap: 0.65rem; border: 1px solid rgba(255, 255, 255, 0.8);">
                <span style="font-size: 1.3rem;">🔥</span>
                <div>
                    <strong style="display: block; font-size: 0.85rem; color: var(--text-main); line-height: 1.2;">Chef Special</strong>
                    <span style="font-size: 0.775rem; color: var(--primary); font-weight: 700;">Artisanal Teriyaki Bowl</span>
                </div>
            </div>

            <!-- Floating Badge Bottom Right -->
            <div style="position: absolute; bottom: 18px; right: 18px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 0.65rem 1rem; border-radius: var(--radius-md); box-shadow: var(--shadow-md); display: flex; align-items: center; gap: 0.65rem; border: 1px solid rgba(255, 255, 255, 0.8);">
                <div style="width: 10px; height: 10px; border-radius: 50%; background: var(--primary); box-shadow: 0 0 0 4px var(--primary-light);"></div>
                <span style="font-size: 0.825rem; font-weight: 700; color: var(--text-main);">Kitchen Live &bull; Accepting Orders</span>
            </div>
        </div>

    </div>
</section>

<!-- =========================================================================
     HOW IT WORKS (3 Simple Steps)
     ========================================================================= -->
<section style="margin-bottom: 4.5rem;">
    <div style="text-align: center; max-width: 650px; margin: 0 auto 3rem;">
        <span style="color: var(--primary); font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.08em; display: block; margin-bottom: 0.5rem;">Simple & Efficient</span>
        <h2 style="font-size: 2.1rem; color: var(--text-main);">How UrbanEats Delivers Excellence</h2>
        <p style="color: var(--text-muted); font-size: 1.05rem;">We eliminated front-of-house bottlenecks to focus purely on exceptional culinary execution.</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.75rem;">
        <div class="card" style="text-align: center; padding: 2.25rem 1.75rem;">
            <div style="width: 56px; height: 56px; border-radius: var(--radius-full); background: var(--primary-light); color: var(--primary); font-size: 1.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-weight: 800; border: 1px solid var(--primary-border);">
                1
            </div>
            <h3 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Browse & Order</h3>
            <p style="color: var(--text-muted); font-size: 0.925rem; line-height: 1.6;">
                Explore chef-curated menus spanning rice bowls, artisanal curries, gourmet grills, and healthy specialties.
            </p>
        </div>

        <div class="card" style="text-align: center; padding: 2.25rem 1.75rem;">
            <div style="width: 56px; height: 56px; border-radius: var(--radius-full); background: var(--primary-light); color: var(--primary); font-size: 1.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-weight: 800; border: 1px solid var(--primary-border);">
                2
            </div>
            <h3 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Cooked Fresh on Demand</h3>
            <p style="color: var(--text-muted); font-size: 0.925rem; line-height: 1.6;">
                Our kitchen line immediately begins preparing your meal using fresh, non-frozen ingredients with zero delays.
            </p>
        </div>

        <div class="card" style="text-align: center; padding: 2.25rem 1.75rem;">
            <div style="width: 56px; height: 56px; border-radius: var(--radius-full); background: var(--primary-light); color: var(--primary); font-size: 1.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-weight: 800; border: 1px solid var(--primary-border);">
                3
            </div>
            <h3 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Fast, Safe Delivery</h3>
            <p style="color: var(--text-muted); font-size: 0.925rem; line-height: 1.6;">
                Dispatched in thermal insulated packaging to guarantee your food arrives steaming hot and ready to enjoy.
            </p>
        </div>
    </div>
</section>

<!-- =========================================================================
     KITCHEN ADVANTAGE SECTION (With Real Kitchen Photograph)
     ========================================================================= -->
<section>
    <div class="kitchen-banner-card">
        <div class="kitchen-banner-grid">
            
            <!-- Kitchen Image (Seamless Edge-to-Edge) -->
            <div class="kitchen-banner-image-wrapper">
                <img src="<?= base_url('images/chef_kitchen.jpg') ?>"
                     alt="Professional Cloud Kitchen Chef"
                     class="kitchen-banner-image">
                <div class="kitchen-banner-overlay"></div>
                <div class="kitchen-banner-badge">
                    <span class="kitchen-banner-badge-dot"></span>
                    <span>Commercial Grade Line &bull; ISO 22000 Ready</span>
                </div>
            </div>

            <!-- Kitchen Story -->
            <div class="kitchen-banner-content">
                <span class="kitchen-banner-eyebrow">
                    Behind the Scenes
                </span>
                <h2 class="kitchen-banner-title">
                    The Cloud Kitchen Revolution
                </h2>
                <p class="kitchen-banner-desc">
                    Traditional restaurants divide attention between noisy dining rooms and rushed kitchens. UrbanEats is 100% delivery-optimized: our state-of-the-art commercial kitchen stations are purpose-built for culinary consistency, speed, and food safety.
                </p>

                <div class="kitchen-banner-features">
                    <div class="kitchen-feature-item">
                        <span class="kitchen-feature-icon">&#10003;</span>
                        <span>Commercial grade stainless steel stations with strict hygiene protocols.</span>
                    </div>
                    <div class="kitchen-feature-item">
                        <span class="kitchen-feature-icon">&#10003;</span>
                        <span>Real-time digital kitchen ticketing that eliminates order errors.</span>
                    </div>
                    <div class="kitchen-feature-item">
                        <span class="kitchen-feature-icon">&#10003;</span>
                        <span>Eco-friendly biodegradable thermal food packaging.</span>
                    </div>
                </div>

                <div>
                    <a href="<?= base_url('auth/register.php') ?>" class="btn btn-primary">
                        Get Started with UrbanEats &rarr;
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
