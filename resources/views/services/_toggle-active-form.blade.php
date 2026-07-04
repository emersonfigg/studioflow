<form method="POST" action="{{ route('services.update', $service) }}" class="inline-flex">
    @csrf
    @method('PATCH')
    <input type="hidden" name="name" value="{{ $service->name }}">
    <input type="hidden" name="description" value="{{ $service->description }}">
    <input type="hidden" name="duration_minutes" value="{{ $service->duration_minutes }}">
    <input type="hidden" name="price" value="{{ number_format((float) $service->price, 2, '.', '') }}">
    <input type="hidden" name="active" value="{{ $service->active ? '0' : '1' }}">
    <input type="hidden" name="is_publicly_available" value="{{ $service->is_publicly_available ? '1' : '0' }}">
    <input type="hidden" name="available_for_pos" value="{{ $service->available_for_pos ? '1' : '0' }}">
    <button type="submit" class="{{ $buttonClass ?? 'sf-button-ghost !px-3 !py-1.5 !text-xs' }}">
        {{ $service->active ? 'Desativar' : 'Ativar' }}
    </button>
</form>
