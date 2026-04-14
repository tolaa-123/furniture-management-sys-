/*
 * Custom Furniture ERP & E-Commerce Platform JavaScript
 * Main script file for the public website
 */

document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling disabled to prevent errors
    // Collection links will work normally without smooth scrolling

    // Navbar background change on scroll
    const header = document.querySelector('header');
    if (header) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }

    // Form submission handling
    const contactForm = document.querySelector('#contact form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(contactForm);
            const formObject = Object.fromEntries(formData.entries());
            
            // Basic validation
            if (!formObject.email || !isValidEmail(formObject.email)) {
                alert('Please enter a valid email address.');
                return;
            }
            
            if (!formObject.firstName || formObject.firstName.trim() === '') {
                alert('Please enter your first name.');
                return;
            }
            
            // In a real application, you would send the data to the server here
            console.log('Form submitted:', formObject);
            alert('Thank you for your message! We will get back to you soon.');
            contactForm.reset();
        });
    }

    // Gallery hover effect enhancement
    const galleryItems = document.querySelectorAll('.hover-effect');
    galleryItems.forEach(item => {
        const img = item.parentElement.querySelector('img');
        if (img) {
            img.addEventListener('mouseenter', () => {
                item.style.opacity = '1';
            });
            
            item.parentElement.addEventListener('mouseleave', () => {
                item.style.opacity = '0';
            });
        }
    });

    // Add fade-in animation to sections when they come into view
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Observe sections with animation class
    document.querySelectorAll('section').forEach(section => {
        observer.observe(section);
    });

    // Handle login/register toggle
    updateAuthButtons();
});

// Utility function to validate email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Utility function to update auth buttons based on session
function updateAuthButtons() {
    // In a real application, you would check the session status from the server
    // For now, we'll simulate it
    const isLoggedIn = sessionStorage.getItem('isLoggedIn') === 'true';
    
    const loginBtn = document.querySelector('.btn-outline-primary[href="#"]');
    const registerBtn = document.querySelector('.btn-primary[href="#"]');
    const dashboardBtn = document.querySelector('.btn-success.d-none');
    
    if (isLoggedIn && loginBtn && registerBtn && dashboardBtn) {
        loginBtn.style.display = 'none';
        registerBtn.style.display = 'none';
        dashboardBtn.classList.remove('d-none');
    }
}

// Function to simulate login
function simulateLogin() {
    sessionStorage.setItem('isLoggedIn', 'true');
    updateAuthButtons();
}

// Function to simulate logout
function simulateLogout() {
    sessionStorage.removeItem('isLoggedIn');
    updateAuthButtons();
}

// Cart functionality (placeholder)
let cart = JSON.parse(sessionStorage.getItem('cart')) || [];

function addToCart(product) {
    // Check if product already in cart
    const existingItem = cart.find(item => item.id === product.id);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({...product, quantity: 1});
    }
    
    sessionStorage.setItem('cart', JSON.stringify(cart));
    updateCartCount();
}

function updateCartCount() {
    const cartCountElement = document.querySelector('.cart-count');
    if (cartCountElement) {
        const totalCount = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartCountElement.textContent = totalCount;
    }
}

// Initialize cart count on page load
document.addEventListener('DOMContentLoaded', updateCartCount);