<div class="ms-card">
    <p class="ms-card-label">
        {{ $stat->label }}
    </p>

    @if($stat->value !== null)
        <p class="ms-card-value">
            {{ number_format($stat->value) }}
        </p>
    @endif

    @if($stat->breakdown !== [])
        <table>
            @foreach($stat->breakdown as $row)
                <tr>
                    <td>
                        {{ $row['label'] }}
                    </td>

                    <td class="ms-muted ms-numeric">
                        {{ number_format($row['count']) }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if($stat->isUnavailable() && $stat->note === null)
        <p class="ms-muted">
            No rows.
        </p>
    @endif

    @if($stat->note !== null)
        <p class="ms-muted">
            {{ $stat->note }}
        </p>
    @endif
</div>
