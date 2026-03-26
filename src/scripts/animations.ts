import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';

// ─── Types ────────────────────────────────────────────────────────────────────

let lenis: Lenis | null = null;

// ─── Reduced Motion Check ─────────────────────────────────────────────────────

function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// ─── Plugin Registration ───────────────────────────────────────────────────────

function registerPlugins(): void {
  gsap.registerPlugin(ScrollTrigger);
}

// ─── Smooth Scroll (desktop-only) ─────────────────────────────────────────────

function initSmoothScroll(): void {
  if (prefersReducedMotion()) return;

  const mq = window.matchMedia('(min-width: 1024px)');
  if (!mq.matches) return;

  lenis = new Lenis({
    duration: 1.2,
    easing: (t: number) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
  });

  gsap.ticker.add((time) => {
    lenis!.raf(time * 1000);
  });

  gsap.ticker.lagSmoothing(0);
}

// ─── Nav Animations ───────────────────────────────────────────────────────────

function initNavAnimations(): void {
  if (prefersReducedMotion()) return;

  const nav = document.getElementById('main-nav');
  const hamburgerBtn = document.getElementById('hamburger-btn');
  const mobileMenu = document.getElementById('mobile-menu');
  const menuClose = document.getElementById('menu-close');
  const line1 = document.querySelector('[data-nav-line="1"]');
  const line2 = document.querySelector('[data-nav-line="2"]');
  const line3 = document.querySelector('[data-nav-line="3"]');
  const mobileLinks = document.querySelectorAll('.mobile-nav-link');
  const navLinks = document.querySelectorAll('.nav-link');

  if (!nav || !hamburgerBtn || !mobileMenu) return;

  // Glassmorphism on scroll: add backdrop-blur + bg when scrolled > 80px
  ScrollTrigger.create({
    start: 80,
    onEnter: () => {
      nav.style.backdropFilter = 'blur(12px)';
      nav.style.backgroundColor = 'rgba(13,11,10,0.85)';
      nav.style.borderBottom = '1px solid rgba(61,46,33,0.6)';
    },
    onLeaveBack: () => {
      nav.style.backdropFilter = '';
      nav.style.backgroundColor = '';
      nav.style.borderBottom = '';
    },
  });

  // Hamburger GSAP timeline (paused)
  const tl = gsap.timeline({ paused: true });

  if (line1 && line2 && line3) {
    tl.to(line1, { rotate: 45, y: 8, duration: 0.3, ease: 'power2.inOut' }, 0)
      .to(line2, { opacity: 0, duration: 0.2, ease: 'power2.inOut' }, 0)
      .to(line3, { rotate: -45, y: -8, duration: 0.3, ease: 'power2.inOut' }, 0);
  }

  let menuOpen = false;

  function openMenu(): void {
    menuOpen = true;
    hamburgerBtn!.setAttribute('aria-expanded', 'true');
    mobileMenu!.style.opacity = '1';
    mobileMenu!.style.pointerEvents = 'auto';
    tl.play();

    // Stagger mobile links in
    if (mobileLinks.length) {
      gsap.from(mobileLinks, {
        opacity: 0,
        y: 40,
        duration: 0.4,
        stagger: 0.06,
        ease: 'power2.out',
      });
    }
  }

  function closeMenu(): void {
    menuOpen = false;
    hamburgerBtn!.setAttribute('aria-expanded', 'false');
    mobileMenu!.style.opacity = '0';
    mobileMenu!.style.pointerEvents = 'none';
    tl.reverse();
  }

  hamburgerBtn.addEventListener('click', () => {
    if (menuOpen) {
      closeMenu();
    } else {
      openMenu();
    }
  });

  if (menuClose) {
    menuClose.addEventListener('click', closeMenu);
  }

  // Close menu when clicking nav links
  mobileLinks.forEach((link) => {
    link.addEventListener('click', closeMenu);
  });

  // Close on desktop nav links click (smooth scroll)
  navLinks.forEach((link) => {
    link.addEventListener('click', () => {
      if (menuOpen) closeMenu();
    });
  });
}

// ─── Hero Animations ──────────────────────────────────────────────────────────

function initHeroAnimations(): void {
  if (prefersReducedMotion()) return;

  const heroTitle = document.querySelector('[data-hero-title]');
  const heroSubtitle = document.querySelector('[data-hero-subtitle]');
  const heroCtas = document.querySelector('[data-hero-ctas]');

  if (heroTitle) {
    gsap.fromTo(heroTitle,
      { opacity: 0, y: 80 },
      { opacity: 1, y: 0, duration: 1, ease: 'power3.out', delay: 0.2 },
    );
  }

  if (heroSubtitle) {
    gsap.fromTo(heroSubtitle,
      { opacity: 0, y: 40 },
      { opacity: 1, y: 0, duration: 0.8, ease: 'power3.out', delay: 0.4 },
    );
  }

  if (heroCtas) {
    gsap.fromTo(heroCtas,
      { opacity: 0, y: 30 },
      { opacity: 1, y: 0, duration: 0.8, ease: 'power3.out', delay: 0.6 },
    );
  }
}

// ─── Scroll Reveal ────────────────────────────────────────────────────────────

function initScrollReveal(): void {
  if (prefersReducedMotion()) return;

  ScrollTrigger.batch('[data-reveal]', {
    start: 'top 85%',
    onEnter: (batch) => {
      gsap.to(batch, {
        opacity: 1,
        y: 0,
        duration: 0.8,
        ease: 'power2.out',
        stagger: 0.1,
      });
    },
    once: true,
  });
}

// ─── Service Animations ───────────────────────────────────────────────────────

function initServiceAnimations(): void {
  if (prefersReducedMotion()) return;

  const serviceBlocks = document.querySelectorAll('[data-service-block]');

  serviceBlocks.forEach((block) => {
    gsap.from(block, {
      opacity: 0,
      y: 60,
      duration: 0.8,
      ease: 'power2.out',
      scrollTrigger: {
        trigger: block,
        start: 'top 80%',
        once: true,
      },
    });
  });
}

// ─── Contact Scrub Animation ──────────────────────────────────────────────────

function initContactAnimation(): void {
  if (prefersReducedMotion()) return;

  const contactTitle = document.querySelector('[data-contact-title]');
  if (!contactTitle) return;

  gsap.from(contactTitle, {
    x: -100,
    opacity: 0,
    scrollTrigger: {
      trigger: contactTitle,
      start: 'top 90%',
      scrub: 1,
    },
  });
}

// ─── Sticky Scale (SectionTitle) ─────────────────────────────────────────────

function initStickyScale(): void {
  if (prefersReducedMotion()) return;

  const stickyElements = document.querySelectorAll('[data-sticky-scale]');

  stickyElements.forEach((el) => {
    const section = el.closest('section');
    if (!section) return;

    gsap.to(el, {
      scale: 0.6,
      transformOrigin: 'left top',
      ease: 'none',
      scrollTrigger: {
        trigger: section,
        start: 'top top',
        end: 'bottom center',
        scrub: true,
      },
    });
  });
}

// ─── Parallax (desktop-only) ──────────────────────────────────────────────────

function initParallax(): void {
  // Intentionally minimal — reserved for future use
  // Desktop-only parallax on selected elements via ScrollTrigger.matchMedia
}

// ─── Public API ───────────────────────────────────────────────────────────────

export function initAnimations(): void {
  registerPlugins();

  // Enable js-enabled class only when GSAP is ready — prevents race condition
  // where CSS hides [data-reveal] elements before GSAP can animate them
  document.documentElement.classList.add('js-enabled');

  initSmoothScroll();
  initNavAnimations();
  initHeroAnimations();
  initScrollReveal();
  initServiceAnimations();
  initContactAnimation();
  initStickyScale();
  initParallax();
}

export function destroyAnimations(): void {
  ScrollTrigger.getAll().forEach((t) => t.kill());
  lenis?.destroy();
  lenis = null;
  gsap.killTweensOf('*');
}
