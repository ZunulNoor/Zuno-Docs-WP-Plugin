<?php
/**
 * Zuno Docs Engine — Precomputed Documentation Graph
 *
 * Builds cached data structures on save/delete for zero-latency frontend.
 *
 * Structures:
 *   - doc_tree:    Hierarchical tree of docs organized by product > category
 *   - doc_index:   Flat map of doc_id => { title, excerpt, product, category, url, headings }
 *   - search_index: Inverted index of terms => doc_ids (ranked by title/heading/content)
 *   - category_map: category_id => { name, slug, doc_count, children }
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

/* -----------------------------------------------------------------------
 * Build hooks (save, delete, term changes)
 * --------------------------------------------------------------------- */
add_action( 'save_post_zuno_doc', 'zuno_docs_build_graph', 10, 2 );
add_action( 'delete_post', 'zuno_docs_on_delete_graph' );
add_action( 'trashed_post', 'zuno_docs_on_trash_untrash_graph' );
add_action( 'untrashed_post', 'zuno_docs_on_trash_untrash_graph' );
add_action( 'edited_zuno_doc_category', 'zuno_docs_build_graph' );
add_action( 'created_zuno_doc_category', 'zuno_docs_build_graph' );
add_action( 'delete_zuno_doc_category', 'zuno_docs_build_graph' );
add_action( 'edited_zuno_product', 'zuno_docs_build_graph' );
add_action( 'created_zuno_product', 'zuno_docs_build_graph' );
add_action( 'delete_zuno_product', 'zuno_docs_build_graph' );

/* -----------------------------------------------------------------------
 * In-memory request cache (Layer 2)
 * The global $zuno_docs_graph_cache is used so that zuno_docs_clear_graph_cache()
 * can invalidate it from outside the function scope.
 * --------------------------------------------------------------------- */
function zuno_docs_get_graph() {
    global $zuno_docs_graph_cache;
    if ( null !== $zuno_docs_graph_cache ) {
        return $zuno_docs_graph_cache;
    }
    $graph = get_option( 'zuno_docs_graph', false );
    if ( ! is_array( $graph ) || empty( $graph ) ) {
        zuno_docs_build_graph();
        $graph = get_option( 'zuno_docs_graph', array() );
    }
    $zuno_docs_graph_cache = $graph;
    return $zuno_docs_graph_cache;
}

function zuno_docs_clear_graph_cache() {
    global $zuno_docs_graph_cache;
    $zuno_docs_graph_cache = null;
}

function zuno_docs_get_product_graph( $category_slug ) {
    $graph = zuno_docs_get_graph();
    if ( is_array( $graph ) && isset( $graph['doc_tree'][ $category_slug ] ) ) {
        return $graph['doc_tree'][ $category_slug ];
    }
    return array(
        'category_slug' => $category_slug,
        'category_name' => '',
        'flat_list'     => array(),
    );
}

/* -----------------------------------------------------------------------
 * Main builder — runs on save_post_zuno_doc
 * --------------------------------------------------------------------- */
function zuno_docs_build_graph() {
    // Clear in-memory cache so anything reading the graph later in this
    // request gets fresh data from the option we're about to update.
    zuno_docs_clear_graph_cache();

    $categories = get_terms( array(
        'taxonomy'   => 'zuno_doc_category',
        'hide_empty' => false,
    ) );

    $doc_tree      = array();
    $search_index  = array();
    $category_map  = array();

    /* ----- Build category map ----- */
    foreach ( $categories as $cat ) {
        $category_map[ $cat->term_id ] = array(
            'id'       => $cat->term_id,
            'name'     => $cat->name,
            'slug'     => $cat->slug,
            'parent'   => $cat->parent,
            'count'    => $cat->count,
            'children' => array(),
        );
    }
    foreach ( $category_map as $id => &$cat ) {
        if ( $cat['parent'] && isset( $category_map[ $cat['parent'] ] ) ) {
            $category_map[ $cat['parent'] ]['children'][] = $id;
        }
    }
    unset( $cat );

    /* ----- Build per-category doc tree and search index (single query) ----- */
    $all_docs = get_posts( array(
        'post_type'      => 'zuno_doc',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ) );

    /* Pre-group docs by category slug */
    $docs_by_category = array();
    foreach ( $categories as $cat ) {
        $docs_by_category[ $cat->slug ] = array();
    }
    foreach ( $all_docs as $doc ) {
        $doc_cats = wp_get_post_terms( $doc->ID, 'zuno_doc_category', array( 'fields' => 'slugs' ) );
        foreach ( $doc_cats as $slug ) {
            if ( isset( $docs_by_category[ $slug ] ) ) {
                $docs_by_category[ $slug ][] = $doc;
            }
        }
    }

    foreach ( $categories as $cat ) {
        $docs = $docs_by_category[ $cat->slug ];
        $flat_list   = array();

        foreach ( $docs as $doc ) {
            $order     = (int) get_post_meta( $doc->ID, '_zuno_doc_order', true );
            $headings  = zuno_docs_extract_headings( $doc->post_content );
            $excerpt   = wp_trim_words( wp_strip_all_tags( $doc->post_content ), 30 );

            $entry = array(
                'id'        => $doc->ID,
                'title'     => $doc->post_title,
                'excerpt'   => $excerpt,
                'slug'      => $doc->post_name,
                'order'     => $order,
                'category'  => $cat->term_id,
                'category_slug' => $cat->slug,
                'category_name' => $cat->name,
                'headings'  => $headings,
                'url'       => add_query_arg( 'zuno_doc', $doc->ID, home_url() ),
            );

            $flat_list[ $doc->ID ] = $entry;

            /* ----- Index terms for search (inverted index) ----- */
            $text = mb_strtolower( $doc->post_title . ' ' . $doc->post_title );
            foreach ( $headings as $h ) {
                $text .= ' ' . mb_strtolower( $h['text'] );
            }
            $text .= ' ' . mb_strtolower( wp_strip_all_tags( $doc->post_content ) );
            $tokens = zuno_docs_tokenize( $text );

            foreach ( $tokens as $token => $weight_mult ) {
                if ( ! isset( $search_index[ $token ] ) ) {
                    $search_index[ $token ] = array();
                }
                $weight = 1;
                $title_lower = mb_strtolower( $doc->post_title );
                if ( false !== mb_strpos( $title_lower, $token ) ) {
                    $weight = 5;
                } else {
                    foreach ( $headings as $h ) {
                        if ( false !== mb_strpos( mb_strtolower( $h['text'] ), $token ) ) {
                            $weight = 3;
                            break;
                        }
                    }
                }
                $search_index[ $token ][] = array( 'id' => $doc->ID, 'weight' => $weight );
            }
        }

        $doc_tree[ $cat->slug ] = array(
            'category_slug'  => $cat->slug,
            'category_name'  => $cat->name,
            'category_id'    => $cat->term_id,
            'flat_list'      => $flat_list,
        );
    }

    /* ----- Store everything ----- */
    $graph = array(
        'doc_tree'      => $doc_tree,
        'search_index'  => $search_index,
        'category_map'  => $category_map,
        'built'         => time(),
    );

    update_option( 'zuno_docs_graph', $graph );

    // Purge external page caches so the frontend renders updated content immediately.
    zuno_docs_purge_page_cache();
}

function zuno_docs_on_delete_graph( $post_id ) {
    if ( 'zuno_doc' !== get_post_type( $post_id ) ) {
        return;
    }
    zuno_docs_build_graph();
}

function zuno_docs_on_trash_untrash_graph( $post_id ) {
    if ( 'zuno_doc' !== get_post_type( $post_id ) ) {
        return;
    }
    zuno_docs_build_graph();
}

/* -----------------------------------------------------------------------
 * Helper: check if a string contains only ASCII characters
 * --------------------------------------------------------------------- */
function zuno_docs_is_ascii( $str ) {
    return (bool) preg_match( '/^[\x00-\x7f]*$/', $str );
}

/* -----------------------------------------------------------------------
 * Tokenizer with n-gram fuzzy support
 * --------------------------------------------------------------------- */
function zuno_docs_tokenize( $text ) {
    $text = preg_replace( '/[^\p{L}\p{N}\s-]/u', ' ', $text );
    $text = preg_replace( '/\s+/', ' ', $text );
    $text = trim( $text );

    $words = explode( ' ', $text );
    $words = array_filter( $words, function( $w ) {
        return mb_strlen( $w ) >= 2;
    } );
    $words = array_slice( $words, 0, 500 );

    $tokens = array();
    foreach ( $words as $word ) {
        $word = mb_substr( $word, 0, 50 );
        $tokens[ $word ] = isset( $tokens[ $word ] ) ? $tokens[ $word ] + 1 : 1;

        $len = mb_strlen( $word );
        if ( $len >= 5 ) {
            $prefix = mb_substr( $word, 0, $len - 2 );
            $tokens[ $prefix ] = isset( $tokens[ $prefix ] ) ? $tokens[ $prefix ] + 1 : 1;
        }
    }

    return $tokens;
}

/* -----------------------------------------------------------------------
 * Search the inverted index
 * --------------------------------------------------------------------- */
function zuno_docs_search( $query, $product_slug = '' ) {
    $graph = zuno_docs_get_graph();
    if ( empty( $graph['search_index'] ) ) {
        return array();
    }

    $query  = mb_strtolower( trim( $query ) );
    $tokens = explode( ' ', $query );
    $tokens = array_filter( $tokens, function( $t ) {
        return mb_strlen( $t ) >= 2;
    } );

    if ( empty( $tokens ) ) {
        return array();
    }

    $index   = $graph['search_index'];
    $results = array();

    foreach ( $tokens as $token ) {
        $token = mb_substr( $token, 0, 50 );

        // Exact match
        if ( isset( $index[ $token ] ) ) {
            foreach ( $index[ $token ] as $entry ) {
                if ( ! isset( $results[ $entry['id'] ] ) ) {
                    $results[ $entry['id'] ] = 0;
                }
                $results[ $entry['id'] ] += $entry['weight'];
            }
        }

        // Fuzzy / prefix match (only for ASCII — levenshtein is not multibyte-safe)
        foreach ( $index as $key => $entries ) {
            if ( $key === $token ) {
                continue;
            }
            if ( ! zuno_docs_is_ascii( $token ) || ! zuno_docs_is_ascii( $key ) ) {
                continue;
            }
            if ( false !== mb_strpos( $key, $token ) || false !== mb_strpos( $token, $key ) ) {
                $lev = levenshtein( $token, mb_substr( $key, 0, mb_strlen( $token ) ) );
                if ( $lev <= 2 ) {
                    foreach ( $entries as $entry ) {
                        if ( ! isset( $results[ $entry['id'] ] ) ) {
                            $results[ $entry['id'] ] = 0;
                        }
                        $results[ $entry['id'] ] += max( 1, $entry['weight'] - $lev );
                    }
                }
            }
        }
    }

    // Sort by score descending
    arsort( $results );

    // Resolve doc data
    $final = array();
    foreach ( $results as $doc_id => $score ) {
        $info = zuno_docs_get_doc_info( $doc_id, $graph );
        if ( $info ) {
            $info['score'] = $score;
            $final[] = $info;
        }
        if ( count( $final ) >= 20 ) {
            break;
        }
    }

    // Filter by product if specified
    if ( $product_slug && ! empty( $final ) ) {
        $final = array_filter( $final, function( $d ) use ( $product_slug ) {
            return isset( $d['product_slug'] ) && $d['product_slug'] === $product_slug;
        } );
        $final = array_values( $final );
    }

    return $final;
}

/* -----------------------------------------------------------------------
 * Resolve doc info from graph
 * --------------------------------------------------------------------- */
function zuno_docs_get_doc_info( $doc_id, $graph = null ) {
    if ( null === $graph ) {
        $graph = zuno_docs_get_graph();
    }

    if ( empty( $graph['doc_tree'] ) || ! is_array( $graph['doc_tree'] ) ) {
        return null;
    }

    foreach ( $graph['doc_tree'] as $slug => $tree ) {
        if ( isset( $tree['flat_list'][ $doc_id ] ) ) {
            $info = $tree['flat_list'][ $doc_id ];
            $info['product_slug'] = $slug;
            $info['product_name'] = $tree['category_name'];
            return $info;
        }
    }

    return null;
}

/* -----------------------------------------------------------------------
 * Extract headings from post content
 * --------------------------------------------------------------------- */
function zuno_docs_extract_headings( $content ) {
    if ( ! $content ) {
        return array();
    }

    $headings = array();
    $pattern  = '/<h([1-6])([^>]*)>(.*?)<\/h\1>/si';

    if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $m ) {
            $level = (int) $m[1];
            $text  = wp_strip_all_tags( $m[3] );
            $text  = trim( $text );
            if ( $text ) {
                $headings[] = array(
                    'level' => $level,
                    'text'  => $text,
                );
            }
        }
    }

    return $headings;
}

/* -----------------------------------------------------------------------
 * Breadcrumb resolver
 * --------------------------------------------------------------------- */
function zuno_docs_get_breadcrumbs( $doc_id, $graph = null ) {
    if ( null === $graph ) {
        $graph = zuno_docs_get_graph();
    }

    $info = zuno_docs_get_doc_info( $doc_id, $graph );
    if ( ! $info ) {
        return array();
    }

    $crumbs = array(
        array(
            'label' => isset( $info['product_name'] ) ? $info['product_name'] : __( 'Documentation', 'zuno-docs-engine' ),
            'slug'  => isset( $info['product_slug'] ) ? $info['product_slug'] : '',
        ),
    );

    if ( ! empty( $info['category'] ) && isset( $graph['category_map'][ $info['category'] ] ) ) {
        $cat_info = $graph['category_map'][ $info['category'] ];
        $crumbs[] = array(
            'label' => $cat_info['name'],
            'slug'  => $cat_info['slug'],
        );
    }

    $crumbs[] = array(
        'label' => $info['title'],
        'slug'  => $info['slug'],
    );

    return $crumbs;
}

/* -----------------------------------------------------------------------
 * Get adjacent docs for prev/next navigation
 * --------------------------------------------------------------------- */
function zuno_docs_get_adjacent( $doc_id, $category_slug ) {
    $tree = zuno_docs_get_product_graph( $category_slug );
    $list = isset( $tree['flat_list'] ) ? $tree['flat_list'] : array();

    if ( empty( $list ) ) {
        return array( 'prev' => null, 'next' => null );
    }

    $ids = array_keys( $list );
    $pos = array_search( $doc_id, $ids, true );

    if ( false === $pos ) {
        return array( 'prev' => null, 'next' => null );
    }

    return array(
        'prev' => $pos > 0 ? $list[ $ids[ $pos - 1 ] ] : null,
        'next' => $pos < count( $ids ) - 1 ? $list[ $ids[ $pos + 1 ] ] : null,
    );
}

/* -----------------------------------------------------------------------
 * Related articles (same category, excluding current)
 * --------------------------------------------------------------------- */
function zuno_docs_get_related( $doc_id, $category_slug, $max = 3 ) {
    $tree   = zuno_docs_get_product_graph( $category_slug );
    $flat   = isset( $tree['flat_list'] ) ? $tree['flat_list'] : array();
    $info   = isset( $flat[ $doc_id ] ) ? $flat[ $doc_id ] : null;

    if ( ! $info ) {
        return array();
    }

    $cat_id = $info['category'] ?? 0;
    $related = array();

    foreach ( $flat as $id => $entry ) {
        if ( $id === $doc_id ) {
            continue;
        }
        if ( $cat_id && isset( $entry['category'] ) && (int) $entry['category'] === (int) $cat_id ) {
            $related[] = $entry;
            if ( count( $related ) >= $max ) {
                break;
            }
        }
    }

    return $related;
}

/* -----------------------------------------------------------------------
 * Force rebuild (admin utility)
 * --------------------------------------------------------------------- */
function zuno_docs_rebuild_graph() {
    delete_option( 'zuno_docs_graph' );
    zuno_docs_build_graph();
}
