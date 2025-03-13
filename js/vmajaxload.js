document.addEventListener('DOMContentLoaded', function () {
    var productContainer = document.querySelector('.product-wrap.grid.ajaxprod');
    if (!productContainer) return;

    var pagination = document.querySelector('ul.pagination');
    if (!pagination) return;

    var currentUrl = window.location.href;
    var startMatch = currentUrl.match(/start=(\d+)/);
    var currentStart = startMatch ? parseInt(startMatch[1]) : 0;
    
    var isLastPage = false;
    var activePageItem = pagination.querySelector('li.active');
    if (activePageItem) {
        var nextElement = activePageItem.nextElementSibling;
        isLastPage = !nextElement || !nextElement.querySelector('a[href*="start="]');
    }

    var nextPageLink = pagination.querySelector('li.active + li a[href*="start="]');
    if (!nextPageLink || isLastPage) return;

    var buttonHtml = `
        <div class="vm-ajax-loader">
            <button class="vm-load-more">
                <div class="vm-loader"></div>
                Загрузить еще
            </button>
        </div>
    `;
    productContainer.insertAdjacentHTML('afterend', buttonHtml);

    var loading = false;
    var button = document.querySelector('.vm-load-more');
    var loadedPages = [currentStart];

    function initProductSliders() {
        jQuery('.vm-trumb-slider:not(.slick-initialized)').each(function() {
            jQuery(this).slick({
                dots: false,
                infinite: true,
                speed: 300,
                slidesToShow: 1,
                adaptiveHeight: true,
                arrows: true,
                lazyLoad: 'ondemand'
            });
        });
    }

    initProductSliders();

    if (button) {
        button.addEventListener('click', function () {
            if (loading) return;

            loading = true;
            button.disabled = true;
            button.classList.add('loading');

            var activePageItem = pagination.querySelector('li.active');
            var nextPageLink = activePageItem ? activePageItem.nextElementSibling.querySelector('a[href*="start="]') : null;
            
            if (!nextPageLink) {
                button.style.display = 'none';
                return;
            }

            var nextPageUrl = nextPageLink.getAttribute('href');
            var startMatch = nextPageUrl.match(/start=(\d+)/);
            var startValue = startMatch ? parseInt(startMatch[1]) : 0;

            if (loadedPages.includes(startValue)) {
                loading = false;
                button.disabled = false;
                button.classList.remove('loading');
                return;
            }

            fetch(nextPageUrl)
                .then(response => response.text())
                .then(html => {
                    var tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;

                    var newProducts = tempDiv.querySelectorAll('.product-block.catprod');
                    if (newProducts.length > 0) {
                        newProducts.forEach(product => {
                            productContainer.appendChild(product);
                        });

                        initProductSliders();

                        if (typeof Virtuemart !== 'undefined') {
                            Virtuemart.product(jQuery('.product'));
                        }

                        var newPagination = tempDiv.querySelector('ul.pagination');
                        if (newPagination) {
                            pagination.innerHTML = newPagination.innerHTML;
                            
                            var newActivePageItem = newPagination.querySelector('li.active');
                            if (newActivePageItem) {
                                var nextElement = newActivePageItem.nextElementSibling;
                                if (!nextElement || !nextElement.querySelector('a[href*="start="]')) {
                                    button.style.display = 'none';
                                }
                            }

                            loadedPages.push(startValue);
                            
                            history.pushState(null, '', nextPageUrl);
                        } else {
                            button.style.display = 'none';
                        }
                    } else {
                        button.style.display = 'none';
                    }
                })
                .catch(error => {
                    button.style.display = 'none';
                })
                .finally(() => {
                    loading = false;
                    button.disabled = false;
                    button.classList.remove('loading');
                });
        });
    }

    window.addEventListener('popstate', function(e) {
        if (e.state !== null && window.location.href.includes('start=')) {
            return;
        }
        window.location.reload();
    });
});