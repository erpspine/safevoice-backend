@component('mail::message')
    # Case Escalation Alert

    @if ($recipient)
        Hello {{ $recipient->name }},
    @else
        Hello,
    @endif

    A case has been escalated and requires your immediate attention.

    ## Case Details

    | | |
    |---|---|
    | **Case Number** | {{ $case->case_token }} |
    | **Type** | {{ ucfirst($case->type) }} |
    | **Status** | {{ ucfirst($case->status) }} |
    | **Submitted** | {{ $case->created_at->format('M d, Y H:i') }} |

    ## Escalation Details

    | | |
    |---|---|
    | **Level** | {{ $escalation->getLevelLabel() }} |
    | **Stage** | {{ ucfirst($escalation->stage) }} |
    | **Overdue By** | {{ $escalation->getFormattedOverdueDuration() }} |
    | **Escalated At** | {{ $escalation->created_at->format('M d, Y H:i') }} |

    **Reason:** {{ $escalation->reason }}

    @if ($case->description)
        ## Case Description

        {{ Str::limit($case->description, 300) }}
    @endif

    @component('mail::button', ['url' => $caseUrl, 'color' => 'red'])
        View Case Details
    @endcomponent

    @if ($escalation->was_reassigned)
        **Note:** This case has been automatically reassigned.
    @endif

    @if ($escalation->priority_changed)
        **Note:** Case priority has been changed from {{ $escalation->old_priority }} to {{ $escalation->new_priority }}.
    @endif

    ---

    Please take action on this case as soon as possible to prevent further escalation.

    Thanks,<br>
    {{ config('app.name') }}

    @slot('subcopy')
        This is an automated escalation notification. If you believe you received this in error, please contact your system
        administrator.
    @endslot
@endcomponent
