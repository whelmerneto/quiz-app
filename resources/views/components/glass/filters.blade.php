{{--
    Refraction filters. Included once per page by layouts/app.blade.php; the
    surfaces reference them by id, so a second copy would duplicate the ids.

    feTurbulence generates a smooth noise field, feGaussianBlur takes the grain
    out of it so the displacement reads as a lens rather than as static, and
    feDisplacementMap pushes each pixel of the source by the red and green
    channels of that field. The result is the uneven bend of thick glass.

    color-interpolation-filters is forced to sRGB. The SVG default is linearRGB,
    which lightens the material noticeably against the page.

    The filter region is widened to 140% because displacement samples outside
    the source box; at the default 120% the edges of a surface fray.
--}}
<svg class="glass-filters" aria-hidden="true" focusable="false" width="0" height="0">
    <defs>
        <filter
            id="glass-refraction"
            x="-20%"
            y="-20%"
            width="140%"
            height="140%"
            color-interpolation-filters="sRGB"
        >
            <feTurbulence type="fractalNoise" baseFrequency="0.009 0.013" numOctaves="2" seed="17" result="field" />
            <feGaussianBlur in="field" stdDeviation="3" result="soft" />
            <feDisplacementMap
                in="SourceGraphic"
                in2="soft"
                scale="24"
                xChannelSelector="R"
                yChannelSelector="G"
            />
        </filter>

        {{-- The same field pushed harder. Swapped in for the length of a
             verdict so the lens flexes at the moment it answers. --}}
        <filter
            id="glass-refraction-flex"
            x="-25%"
            y="-25%"
            width="150%"
            height="150%"
            color-interpolation-filters="sRGB"
        >
            <feTurbulence type="fractalNoise" baseFrequency="0.009 0.013" numOctaves="2" seed="17" result="field" />
            <feGaussianBlur in="field" stdDeviation="3" result="soft" />
            <feDisplacementMap
                in="SourceGraphic"
                in2="soft"
                scale="62"
                xChannelSelector="R"
                yChannelSelector="G"
            />
        </filter>
    </defs>
</svg>
