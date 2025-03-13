document.addEventListener('DOMContentLoaded', function () {
    var productContainer = document.querySelector('.product-wrap.grid.ajaxprod');
    if (!productContainer) return;

    var pagination = document.querySelector('ul.pagination');
    if (!pagination) return;

    var isLastPage = false;
    var activePageItem = pagination.querySelector('li.active');
    if (activePageItem) {
        var nextElement = activePageItem.nextElementSibling;
        isLastPage = !nextElement || !nextElement.querySelector('a[href*="start="]');
    }

    var nextPageLink = pagination.querySelector('li:not(.active) a[href*="start="]');
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

            nextPageLink = pagination.querySelector('li:not(.active) a[href*="start="]');
            if (!nextPageLink) {
                button.style.display = 'none';
                return;
            }

            var nextPageUrl = nextPageLink.getAttribute('href');

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
                            
                            var activePageItem = newPagination.querySelector('li.active');
                            if (activePageItem) {
                                var nextElement = activePageItem.nextElementSibling;
                                if (!nextElement || !nextElement.querySelector('a[href*="start="]')) {
                                    button.style.display = 'none';
                                }
                            }
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
});