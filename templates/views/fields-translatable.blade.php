@if($HAS_TRANSLATION_FIELDS$)
    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">{{ trans('core::core.title.translatable fields') }}</h3>
        </div>
        <div class="box-body">
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <?php $i = 0; ?>
                    <?php foreach (LaravelLocalization::getSupportedLocales() as $locale => $language): ?>
                    <?php $i++; ?>
                    <li class="{{ App::getLocale() == $locale ? 'active' : '' }}">
                        <a href="#tab_{{ $i }}" data-toggle="tab">{{ trans('core::core.tab.'. strtolower($language['name'])) }}</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="tab-content">
                    <?php $i = 0; ?>
                    <?php foreach (LaravelLocalization::getSupportedLocales() as $locale => $language): ?>
                    <?php $i++; ?>
                    <?php $lang = $locale; ?>
                    <div class="tab-pane {{ App::getLocale() == $locale ? 'active' : '' }}" id="tab_{{ $i }}">
                        <div class="box-body">
                            <p>
                                $FIELDS$
                            </p>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
@endif