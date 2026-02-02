import { z } from "zod";

export const examSchema = z.object({
  title: z.string().min(1, "Title cannot be empty").max(255, "Title cannot exceed 255 characters"),

  maxAttempts: z
    .number({
      message: "Max attempts must be an integer",
    })
    .int("Max attempts must be an integer")
    .min(1, "Max attempts must be between 1 and 1000")
    .max(1000, "Max attempts must be between 1 and 1000"),

  cooldownMinutes: z
    .number({
      message: "Cooldown minutes must be an integer",
    })
    .int("Cooldown minutes must be an integer")
    .min(0, "Cooldown minutes must be between 0 and 525600")
    .max(525600, "Cooldown minutes must be between 0 and 525600"),
});

export type ExamFormData = z.infer<typeof examSchema>;
