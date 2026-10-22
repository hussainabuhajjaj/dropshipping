import { ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import axios from 'axios'

export function useProductReviews(product, t) {
  const page = usePage()
  const reviewNotice = ref(page.props.flash?.review_notice ?? '')
  const reviewsState = ref([...(page.props.reviews ?? [])])
  const votedHelpfulIds = ref(new Set())
  const helpfulLoadingId = ref(null)
  const imagesError = ref('')

  const reviewForm = useForm({
    order_item_id: page.props.reviewableItems?.[0]?.id ?? null,
    rating: 5,
    title: '',
    body: '',
    images: [],
  })

  const onImagesChange = (event) => {
    const files = Array.from(event.target?.files ?? [])
    const images = files.filter((file) => file.type?.startsWith('image/'))

    if (images.length > 3) {
      imagesError.value = t('Attach up to 3 images')
    } else {
      imagesError.value = ''
    }

    const trimmed = images.slice(0, 3)
    const tooLarge = trimmed.find((file) => file.size > 3 * 1024 * 1024)
    if (tooLarge) {
      imagesError.value = t('Each image must be under 3MB')
    }

    reviewForm.images = tooLarge ? [] : trimmed
  }

  const submitReview = () => {
    if (!reviewForm.order_item_id) return

    reviewForm.post(route('products.reviews.store', { product: product.value?.slug }), {
      preserveScroll: true,
      onSuccess: () => {
        reviewNotice.value = page.props.flash?.review_notice ?? t('Thanks for your review.')
        reviewForm.reset('title', 'body', 'images')
        imagesError.value = ''
      },
    })
  }

  const markVoted = (id) => {
    const next = new Set(votedHelpfulIds.value)
    next.add(id)
    votedHelpfulIds.value = next
  }

  const isReviewVoted = (id) => votedHelpfulIds.value.has(id)

  const voteHelpful = async (review) => {
    if (!review?.id || isReviewVoted(review.id) || helpfulLoadingId.value === review.id) return

    helpfulLoadingId.value = review.id

    try {
      const { data } = await axios.post(route('reviews.helpful', { review: review.id }))
      reviewsState.value = reviewsState.value.map((r) =>
        r.id === review.id
          ? { ...r, helpful_count: data.helpful_count ?? r.helpful_count ?? 0 }
          : r,
      )
      markVoted(review.id)
    } catch (error) {
      if (error?.response?.status === 409) {
        markVoted(review.id)
      }
    } finally {
      helpfulLoadingId.value = null
    }
  }

  const reviewBarWidth = (rating) => {
    const summary = page.props.reviewSummary
    if (!summary?.count) return 0
    return Math.round(((summary.breakdown?.[rating] ?? 0) / summary.count) * 100)
  }

  return {
    reviewForm,
    reviewNotice,
    reviewsState,
    votedHelpfulIds,
    helpfulLoadingId,
    imagesError,
    onImagesChange,
    submitReview,
    voteHelpful,
    isReviewVoted,
    reviewBarWidth,
  }
}
