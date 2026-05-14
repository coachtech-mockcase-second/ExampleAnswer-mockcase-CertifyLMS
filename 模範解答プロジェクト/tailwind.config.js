/**
 * Certify LMS — Tailwind config
 * Theme: Tropical Emerald (Sunrise Amber → Mint → Teal Aqua)
 * Token values mirror resources/css/app.css. Keep both in sync.
 */
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/View/Components/**/*.php',
    ],

    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#F0FDFA', 100: '#CCFBF1', 200: '#99F6E4', 300: '#5EEAD4',
                    400: '#2DD4BF', 500: '#14B8A6', 600: '#0D9488', 700: '#0F766E',
                    800: '#115E59', 900: '#134E4A', 950: '#042F2E',
                },
                secondary: {
                    50: '#F4F0FF', 100: '#E8DEFF', 200: '#D2BDFF', 300: '#B393FF',
                    400: '#9466FB', 500: '#8443F2', 600: '#7C3AED', 700: '#6724D4',
                    800: '#531CAA', 900: '#401686', 950: '#260A55',
                },
                success: {
                    50: '#ECFDF5', 100: '#D1FAE5', 200: '#A7F3D0', 300: '#6EE7B7',
                    400: '#34D399', 500: '#14C088', 600: '#10B981', 700: '#047857',
                    800: '#065F46', 900: '#064E3B', 950: '#022C22',
                },
                warning: {
                    50: '#FFFBEB', 100: '#FEF3C7', 200: '#FDE68A', 300: '#FCD34D',
                    400: '#FBBF24', 500: '#F59E0B', 600: '#D97706', 700: '#B45309',
                    800: '#92400E', 900: '#78350F', 950: '#451A03',
                },
                danger: {
                    50: '#FFF1F2', 100: '#FFE4E6', 200: '#FECDD3', 300: '#FDA4AF',
                    400: '#FB7185', 500: '#F43F5E', 600: '#E11D48', 700: '#BE123C',
                    800: '#9F1239', 900: '#881337', 950: '#4C0519',
                },
                info: {
                    50: '#F0F9FF', 100: '#E0F2FE', 200: '#BAE6FD', 300: '#7DD3FC',
                    400: '#38BDF8', 500: '#0EA5E9', 600: '#0284C7', 700: '#0369A1',
                    800: '#075985', 900: '#0C4A6E', 950: '#082F49',
                },
                ink: {
                    50: '#F3F7F6', 100: '#E6EDEB', 200: '#D2DEDB', 300: '#B5C7C3',
                    400: '#93AAA6', 500: '#6B8783', 600: '#466662', 700: '#2C4A45',
                    900: '#0F2E2A', 950: '#0B1F1D',
                },
                surface: {
                    canvas: '#F4FBFA',
                    raised: '#FFFFFF',
                    sunken: '#EAF6F4',
                },
            },
            fontFamily: {
                display: ['"Bricolage Grotesque"', '"Noto Sans JP"', 'Inter', 'sans-serif'],
                sans: ['Inter', '"Noto Sans JP"', 'system-ui', 'sans-serif'],
                mono: ['"JetBrains Mono"', 'SFMono-Regular', 'Menlo', 'monospace'],
                pdf: ['IPAGothic', '"Noto Sans JP"', 'sans-serif'],
            },
            borderRadius: {
                DEFAULT: '8px',
                sm: '4px',
                md: '10px',
                lg: '14px',
                xl: '16px',
                '2xl': '20px',
                '3xl': '24px',
            },
            boxShadow: {
                xs: '0 1px 1px rgba(13, 148, 136, 0.04)',
                sm: '0 1px 2px rgba(13, 148, 136, 0.06), 0 1px 1px rgba(15, 46, 42, 0.04)',
                DEFAULT: '0 2px 8px rgba(13, 148, 136, 0.06), 0 1px 2px rgba(15, 46, 42, 0.05)',
                md: '0 8px 24px -8px rgba(13, 148, 136, 0.15), 0 2px 6px rgba(15, 46, 42, 0.06)',
                lg: '0 16px 36px -12px rgba(13, 148, 136, 0.18), 0 4px 10px rgba(15, 46, 42, 0.08)',
                xl: '0 24px 48px -16px rgba(13, 148, 136, 0.22), 0 8px 16px rgba(15, 46, 42, 0.10)',
                glow: '0 0 0 4px rgba(20, 184, 166, 0.20)',
                'glow-violet': '0 0 0 4px rgba(124, 58, 237, 0.18)',
                'glow-warning': '0 0 0 4px rgba(245, 158, 11, 0.22)',
            },
            backgroundImage: {
                'gradient-tropic': 'linear-gradient(135deg, #FCD34D 0%, #6EE7B7 50%, #5EEAD4 100%)',
                'gradient-tropic-soft': 'linear-gradient(135deg, #FEF3C7 0%, #A7F3D0 45%, #99F6E4 100%)',
                'gradient-celebrate': 'linear-gradient(135deg, #FBBF24 0%, #10B981 100%)',
                'gradient-warn-coral': 'linear-gradient(135deg, #FBBF24 0%, #F59E0B 50%, #E11D48 100%)',
            },
            transitionTimingFunction: {
                'out-quint': 'cubic-bezier(0.22, 1, 0.36, 1)',
                emphasized: 'cubic-bezier(0.16, 1, 0.3, 1)',
            },
            transitionDuration: {
                fast: '120ms',
                normal: '200ms',
                slow: '320ms',
            },
        },
    },

    plugins: [forms, typography],
};
