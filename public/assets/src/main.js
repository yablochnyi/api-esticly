document.addEventListener('DOMContentLoaded', function() {
  const grid = document.querySelector('.reviews__grid');
  const dots = document.querySelectorAll('.reviews__dot');
  const prevBtn = document.querySelector('.reviews__pagination-btn--prev');
  const nextBtn = document.querySelector('.reviews__pagination-btn--next');
  const cards = document.querySelectorAll('.reviews__card');
  
  if (!dots.length || !grid || !cards.length) return;
  
  let currentIndex = 0;
  
  function setActive(index) {
    dots.forEach((dot, i) => {
      if (i === index) {
        dot.classList.add('reviews__dot--active');
      } else {
        dot.classList.remove('reviews__dot--active');
      }
    });
    currentIndex = index;
  }

  function scrollToCard(index) {
    const card = cards[index];
    if (!card) return;

    grid.scrollTo({
      left: card.offsetLeft,
      behavior: 'smooth',
    });
  }
  
  dots.forEach((dot, index) => {
    dot.addEventListener('click', () => {
      setActive(index);
      scrollToCard(index);
    });
  });
  
  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      const newIndex = (currentIndex - 1 + dots.length) % dots.length;
      setActive(newIndex);
      scrollToCard(newIndex);
    });
  }
  
  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      const newIndex = (currentIndex + 1) % dots.length;
      setActive(newIndex);
      scrollToCard(newIndex);
    });
  }

  let scrollTicking = false;

  grid.addEventListener('scroll', () => {
    if (scrollTicking) return;

    scrollTicking = true;

    window.requestAnimationFrame(() => {
      const gridLeft = grid.scrollLeft;
      let nearestIndex = 0;
      let nearestDistance = Number.POSITIVE_INFINITY;

      cards.forEach((card, index) => {
        const distance = Math.abs(card.offsetLeft - gridLeft);
        if (distance < nearestDistance) {
          nearestDistance = distance;
          nearestIndex = index;
        }
      });

      setActive(nearestIndex);
      scrollTicking = false;
    });
  }, { passive: true });
});



document.addEventListener('DOMContentLoaded', function() {
  const faqItems = document.querySelectorAll('.faq__item');
  
  if (!faqItems.length) return;
  
  faqItems.forEach(item => {
    const question = item.querySelector('.faq__question');
    
    question.addEventListener('click', () => {
      const isActive = item.classList.contains('active');
      
      faqItems.forEach(otherItem => {
        otherItem.classList.remove('active');
      });
      
      if (!isActive) {
        item.classList.add('active');
      }
    });
  });
});

const menuBtn = document.getElementById('menuBtn');
const closeBtn = document.getElementById('closeBtn');
const mobileMenu = document.getElementById('mobileMenu');

if (menuBtn && mobileMenu) {
  menuBtn.addEventListener('click', () => {
    menuBtn.classList.toggle('active');
    mobileMenu.classList.toggle('active');
  });
}

if (closeBtn && menuBtn && mobileMenu) {
  closeBtn.addEventListener('click', () => {
    menuBtn.classList.remove('active');
    mobileMenu.classList.remove('active');
  });
}

const links = document.querySelectorAll('.header__link');
links.forEach(link => {
  link.addEventListener('click', () => {
    menuBtn?.classList.remove('active');
    mobileMenu?.classList.remove('active');
  });
});
